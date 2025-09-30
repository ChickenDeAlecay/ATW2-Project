<?php 
require_once 'config.php'; 
require_once 'auth.php';
require_once 'reviews.php';

$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bristol Trees Map</title>
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>
    <?php if ($user): ?>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($user['display_name']); ?>!
            <?php if ($user['is_admin']): ?>
                <a href="admin.php">Admin Portal</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    <?php else: ?>
        <div class="user-info">
            <a href="login.php">Login / Register</a>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filter Box -->
    <div class="search-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0; color: #228b22;">🔍 Search & Filter Trees</h4>
            <button id="toggle-info" class="filter-btn" onclick="toggleInfoPanel()" title="Toggle info panel">ℹ️</button>
        </div>
        <input type="text" id="tree-search" class="search-input" placeholder="🌳 Search by name, type, or Latin name...">
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">All Trees</button>
            <button class="filter-btn" data-filter="alive">🌿 Alive</button>
            <button class="filter-btn" data-filter="dead">🍂 Dead</button>
        </div>
        <div style="font-size: 12px; margin-top: 8px; color: #666; border-top: 1px solid #eee; padding-top: 8px;">
            <div><strong><span id="visible-count">0</span></strong> visible of <strong><span id="total-count">0</span></strong> trees</div>
            <div id="tree-count" style="margin-top: 4px; font-style: italic;">Loading trees...</div>
            <div style="margin-top: 4px; font-size: 11px; color: #999;">
                💡 Tip: Use Ctrl+F to quickly focus search
            </div>
        </div>
    </div>
    
    <!-- Notification area -->
    <div id="notification" class="notification"></div>
    
    <div class="info-panel" style="display: none;">
        <h3>Bristol Trees Map</h3>
        <p>Click on a tree marker to see details</p>
        <div id="tree-count">Loading trees...</div>
        <div class="tree-info" id="tree-info" style="display: none;">
            <h4 id="tree-title"></h4>
            <p><strong>Type:</strong> <span id="tree-type"></span></p>
            <p><strong>Latin Name:</strong> <span id="tree-latin"></span></p>
            <p><strong>Common Name:</strong> <span id="tree-common"></span></p>
            <p><strong>Crown Height:</strong> <span id="tree-height"></span>m</p>
            <p><strong>Crown Width:</strong> <span id="tree-width"></span>m</p>
            <p><strong>Status:</strong> <span id="tree-status"></span></p>
        </div>
        <div class="tree-list" id="tree-list" style="display: none;">
            <h4>Available Trees:</h4>
            <div id="tree-items"></div>
        </div>
    </div>
    
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <div>Loading tree data...</div>
        <div id="loading-progress" style="font-size: 12px; color: #666; margin-top: 5px;"></div>
    </div>
    
    <div class="error-message" id="error-message">
        <h4>Google Maps API Key Required</h4>
        <p>To display the interactive map, please:</p>
        <ol>
            <li>Get a Google Maps API key from <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Update the <code>config.php</code> file with your API key</li>
            <li>Refresh this page</li>
        </ol>
        <p><strong>Current status:</strong> Using sample tree data for demonstration</p>
    </div>
    
    <div id="map"></div>
    
    <!-- Tree Details Modal -->
    <div id="tree-modal" class="tree-modal">
        <div class="modal-content">
            <span class="close" onclick="closeTreeModal()">&times;</span>
            <div id="modal-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        let map;
        let markers = [];
        let trees = [];
        let visibleTrees = new Set(); // Track which trees are currently visible

        function initMap() {
            // Center map on Bristol
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: <?php echo DEFAULT_ZOOM; ?>,
                center: { lat: <?php echo DEFAULT_LAT; ?>, lng: <?php echo DEFAULT_LNG; ?> }, // Bristol coordinates
            });

            // Add event listeners for map movement and zoom
            map.addListener('bounds_changed', () => {
                // Debounce the update to avoid too many calls
                clearTimeout(window.boundsChangeTimeout);
                window.boundsChangeTimeout = setTimeout(() => {
                    updateVisibleMarkers();
                }, 300);
            });

            loadTrees();
        }
        
        function handleMapError() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('error-message').style.display = 'block';
            // Still load tree data for demonstration
            loadTrees();
        }
        
        function showTreeList() {
            const treeItems = document.getElementById('tree-items');
            treeItems.innerHTML = '';
            
            trees.forEach((tree, index) => {
                const item = document.createElement('div');
                item.className = `tree-item ${tree.attributes.DEAD === 'Y' ? 'dead' : 'alive'}`;
                item.innerHTML = `
                    <strong>${tree.attributes.FULL_COMMON_NAME || 'Unknown Tree'}</strong><br>
                    <small>${tree.attributes.LATIN_NAME || 'N/A'} - ${tree.attributes.DEAD === 'Y' ? 'Dead' : 'Alive'}</small>
                `;
                item.onclick = () => showTreeInfo(tree.attributes);
                treeItems.appendChild(item);
            });
            
            document.getElementById('tree-list').style.display = 'block';
        }
        
        // Handle Google Maps API loading errors
        window.gm_authFailure = handleMapError;

        async function loadTrees() {
            try {
                document.getElementById('loading-progress').textContent = 'Fetching tree data...';
                
                const response = await fetch('api.php');
                const data = await response.json();
                
                if (data.features) {
                    trees = data.features;
                    document.getElementById('total-count').textContent = trees.length;
                    displayTrees();
                    document.getElementById('tree-count').textContent = `${trees.length} trees loaded`;
                    
                    // Initialize search and filters
                    initializeSearchAndFilters();
                    showNotification('Trees loaded successfully!', 'success');
                } else {
                    document.getElementById('tree-count').textContent = 'Error loading tree data';
                    showNotification('Error loading tree data', 'error');
                }
            } catch (error) {
                console.error('Error loading trees:', error);
                document.getElementById('tree-count').textContent = 'Error loading tree data';
                showNotification('Failed to load tree data', 'error');
            }
            
            document.getElementById('loading').style.display = 'none';
        }
        
        // Search and filter functionality
        let currentFilter = 'all';
        let currentSearch = '';
        
        function initializeSearchAndFilters() {
            // Search input
            const searchInput = document.getElementById('tree-search');
            searchInput.addEventListener('input', (e) => {
                currentSearch = e.target.value.toLowerCase();
                updateVisibleMarkers();
            });
            
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    currentFilter = e.target.dataset.filter;
                    updateVisibleMarkers();
                });
            });
        }
        
        function shouldShowTree(tree) {
            // Apply filter
            if (currentFilter === 'alive' && tree.attributes.DEAD === 'Y') return false;
            if (currentFilter === 'dead' && tree.attributes.DEAD !== 'Y') return false;
            
            // Apply search
            if (currentSearch) {
                const searchFields = [
                    tree.attributes.FULL_COMMON_NAME || '',
                    tree.attributes.LATIN_NAME || '',
                    tree.attributes.TYPE || ''
                ].join(' ').toLowerCase();
                
                if (!searchFields.includes(currentSearch)) return false;
            }
            
            return true;
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        function toggleInfoPanel() {
            const panel = document.querySelector('.info-panel');
            const isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
        }

        function displayTrees() {
            // If no map is available, show tree list instead
            if (!map) {
                showTreeList();
                // Show first tree's info by default
                if (trees.length > 0) {
                    showTreeInfo(trees[0].attributes);
                }
                return;
            }
            
            // Initial update of visible markers
            updateVisibleMarkers();
        }

        function updateVisibleMarkers() {
            if (!map || !trees.length) return;

            const bounds = map.getBounds();
            if (!bounds) return;

            const currentZoom = map.getZoom();
            
            // Adjust marker density based on zoom level
            let skipFactor = 1;
            if (currentZoom < 14) {
                skipFactor = Math.floor(Math.pow(2, 15 - currentZoom)); // Show fewer markers when zoomed out
            }

            // Clear existing markers that are outside bounds
            markers.forEach(marker => {
                const position = marker.getPosition();
                if (!bounds.contains(position)) {
                    marker.setMap(null);
                    visibleTrees.delete(marker.treeIndex);
                }
            });

            // Remove cleared markers from array
            markers = markers.filter(marker => marker.getMap() !== null);

            let addedCount = 0;
            const maxMarkersPerUpdate = 500; // Limit markers per update for performance

            // Add markers for trees in current viewport
            trees.forEach((tree, index) => {
                if (addedCount >= maxMarkersPerUpdate) return;
                
                // Skip some trees when zoomed out for better performance
                if (index % skipFactor !== 0) return;
                
                // Apply search and filter
                if (!shouldShowTree(tree)) return;

                if (tree.geometry && tree.geometry.x && tree.geometry.y) {
                    const position = new google.maps.LatLng(tree.geometry.y, tree.geometry.x);
                    
                    // Check if tree is in viewport and not already visible
                    if (bounds.contains(position) && !visibleTrees.has(index)) {
                        const marker = new google.maps.Marker({
                            position: position,
                            map: map,
                            title: tree.attributes.FULL_COMMON_NAME || 'Unknown tree',
                            icon: {
                                url: getTreeIcon(tree.attributes),
                                scaledSize: new google.maps.Size(16, 16)
                            }
                        });

                        marker.treeIndex = index; // Store index for tracking
                        marker.addListener("click", () => {
                            showTreeModal(tree);
                        });

                        markers.push(marker);
                        visibleTrees.add(index);
                        addedCount++;
                    }
                }
            });

            // Update tree count display
            const visibleCount = markers.length;
            const totalCount = trees.length;
            const filteredCount = trees.filter(shouldShowTree).length;
            
            document.getElementById('visible-count').textContent = visibleCount;
            document.getElementById('tree-count').textContent = 
                `Showing ${visibleCount} of ${filteredCount} trees (zoom: ${currentZoom})`;
        }

        function getTreeIcon(attributes) {
            // Return different icons based on tree status
            if (attributes.DEAD === 'Y') {
                return './icons/dead_tree.png';
            } else {
                return './icons/alive_tree.png';
            }
        }

        function showTreeInfo(attributes) {
            document.getElementById('tree-title').textContent = attributes.FULL_COMMON_NAME || 'Unknown Tree';
            document.getElementById('tree-type').textContent = attributes.TYPE || 'N/A';
            document.getElementById('tree-latin').textContent = attributes.LATIN_NAME || 'N/A';
            document.getElementById('tree-common').textContent = attributes.FULL_COMMON_NAME || 'N/A';
            document.getElementById('tree-height').textContent = attributes.CROWN_HEIGHT || 'N/A';
            document.getElementById('tree-width').textContent = attributes.CROWN_WIDTH || 'N/A';
            document.getElementById('tree-status').textContent = attributes.DEAD === 'Y' ? 'Dead' : 'Alive';
            
            document.getElementById('tree-info').style.display = 'block';
        }
        
        async function showTreeModal(tree) {
            const modal = document.getElementById('tree-modal');
            const content = document.getElementById('modal-content');
            
            // Show loading state
            content.innerHTML = '<p>Loading tree details...</p>';
            modal.style.display = 'block';
            
            // Log tree view for analytics
            fetch('analytics.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'tree_id=' + encodeURIComponent(tree.attributes.ASSET_ID)
            }).catch(() => {}); // Ignore errors for analytics
            
            try {
                // Fetch tree details including reviews and images
                const response = await fetch(`tree_details.php?tree_id=${encodeURIComponent(tree.attributes.ASSET_ID)}`);
                const data = await response.json();
                
                // Build modal content
                let html = `
                    <h2>${tree.attributes.FULL_COMMON_NAME || 'Unknown Tree'}</h2>
                    <div class="tree-details">
                        <p><strong>Type:</strong> ${tree.attributes.TYPE || 'N/A'}</p>
                        <p><strong>Latin Name:</strong> ${tree.attributes.LATIN_NAME || 'N/A'}</p>
                        <p><strong>Crown Height:</strong> ${tree.attributes.CROWN_HEIGHT || 'N/A'}m</p>
                        <p><strong>Crown Width:</strong> ${tree.attributes.CROWN_WIDTH || 'N/A'}m</p>
                        <p><strong>Status:</strong> ${tree.attributes.DEAD === 'Y' ? 'Dead' : 'Alive'}</p>
                `;
                
                // Show average rating
                if (data.average_rating && data.average_rating.review_count > 0) {
                    const avgRating = parseFloat(data.average_rating.avg_rating);
                    html += `
                        <p><strong>Average Rating:</strong> 
                        <span class="review-rating">${'★'.repeat(Math.round(avgRating))}${'☆'.repeat(5 - Math.round(avgRating))}</span>
                        (${avgRating.toFixed(1)}/5 from ${data.average_rating.review_count} reviews)</p>
                    `;
                }
                
                html += '</div>';
                
                // Show images
                if (data.images && data.images.length > 0) {
                    html += '<div class="tree-images"><h3>Photos</h3><div class="image-gallery">';
                    data.images.forEach(image => {
                        html += `<img src="uploads/trees/${image.filename}" alt="Tree photo" class="tree-image" onclick="openImageModal('uploads/trees/${image.filename}')">`;
                    });
                    html += '</div></div>';
                }
                
                // Review section (only for logged-in users)
                <?php if ($user): ?>
                html += `
                    <div class="review-form">
                        <h3>Rate this Tree</h3>
                        <form id="review-form" onsubmit="submitReview(event, '${tree.attributes.ASSET_ID}')">
                            <div class="star-rating" id="star-rating">
                                ${[1,2,3,4,5].map(i => `<span class="star" data-rating="${i}" onclick="setRating(${i})">★</span>`).join('')}
                            </div>
                            <textarea name="comment" placeholder="Leave a comment (optional)" rows="3"></textarea>
                            <button type="submit" class="btn">Submit Review</button>
                        </form>
                    </div>
                    
                    <div class="upload-section">
                        <h3>Upload Photo</h3>
                        <form id="upload-form" onsubmit="uploadImage(event, '${tree.attributes.ASSET_ID}')" enctype="multipart/form-data">
                            <input type="file" name="image" accept="image/*" required>
                            <button type="submit" class="btn">Upload Photo</button>
                        </form>
                        <small>Photos will be reviewed by an admin before appearing on the site.</small>
                    </div>
                `;
                <?php endif; ?>
                
                // Show existing reviews
                if (data.reviews && data.reviews.length > 0) {
                    html += '<div class="existing-reviews"><h3>Reviews</h3>';
                    data.reviews.forEach(review => {
                        html += `
                            <div class="review-item">
                                <div class="review-header">
                                    <strong>${review.display_name}</strong>
                                    <span class="review-rating">${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</span>
                                </div>
                                ${review.comment ? `<p>${review.comment}</p>` : ''}
                                <small>${new Date(review.created_at).toLocaleDateString()}</small>
                            </div>
                        `;
                    });
                    html += '</div>';
                }
                
                content.innerHTML = html;
                
                // Set current user's rating if exists
                if (data.user_review) {
                    setRating(data.user_review.rating);
                    if (data.user_review.comment) {
                        document.querySelector('textarea[name="comment"]').value = data.user_review.comment;
                    }
                }
                
            } catch (error) {
                content.innerHTML = '<p>Error loading tree details. Please try again.</p>';
                console.error('Error loading tree details:', error);
            }
        }
        
        function closeTreeModal() {
            document.getElementById('tree-modal').style.display = 'none';
        }
        
        function setRating(rating) {
            const stars = document.querySelectorAll('#star-rating .star');
            stars.forEach((star, index) => {
                star.classList.toggle('active', index < rating);
            });
            // Store the rating
            document.getElementById('star-rating').dataset.rating = rating;
        }
        
        async function submitReview(event, treeId) {
            event.preventDefault();
            
            const rating = document.getElementById('star-rating').dataset.rating;
            const comment = event.target.comment.value;
            
            if (!rating) {
                alert('Please select a rating');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_review');
                formData.append('tree_id', treeId);
                formData.append('rating', rating);
                formData.append('comment', comment);
                
                const response = await fetch('tree_actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Review saved successfully!', 'success');
                    // Refresh modal content
                    const tree = trees.find(t => t.attributes.ASSET_ID === treeId);
                    if (tree) showTreeModal(tree);
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            } catch (error) {
                showNotification('Error submitting review', 'error');
                console.error('Error:', error);
            }
        }
        
        async function uploadImage(event, treeId) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'upload_image');
            formData.append('tree_id', treeId);
            
            // Show uploading notification
            showNotification('Uploading image...', 'info');
            
            try {
                const response = await fetch('tree_actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                showNotification(result.message, result.success ? 'success' : 'error');
                
                if (result.success) {
                    event.target.reset();
                }
            } catch (error) {
                showNotification('Error uploading image', 'error');
                console.error('Error:', error);
            }
        }
        
        function openImageModal(src) {
            // Simple image modal - you can enhance this
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.8); z-index: 3000; display: flex; 
                align-items: center; justify-content: center; cursor: pointer;
            `;
            
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px;';
            
            modal.appendChild(img);
            modal.onclick = () => document.body.removeChild(modal);
            document.body.appendChild(modal);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('tree-modal');
            if (event.target === modal) {
                closeTreeModal();
            }
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key closes modal
            if (e.key === 'Escape') {
                closeTreeModal();
            }
            // Ctrl/Cmd + F focuses search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('tree-search').focus();
            }
        });
        
        // Add URL parameter handling for direct tree linking
        function checkUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const treeId = urlParams.get('tree');
            if (treeId && trees.length > 0) {
                const tree = trees.find(t => t.attributes.ASSET_ID === treeId);
                if (tree) {
                    // Center map on tree and show modal
                    map.setCenter({lat: tree.geometry.y, lng: tree.geometry.x});
                    map.setZoom(18);
                    setTimeout(() => showTreeModal(tree), 500);
                }
            }
        }
        
        // Initialize URL checking after trees load
        let treesLoaded = false;
        const originalDisplayTrees = displayTrees;
        displayTrees = function() {
            originalDisplayTrees.call(this);
            if (!treesLoaded) {
                treesLoaded = true;
                checkUrlParams();
            }
        };
    </script>
    
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap" onerror="handleMapError()"></script>
    
    <script>
        // Fallback if Google Maps script fails to load
        setTimeout(function() {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                handleMapError();
            }
        }, 5000);
    </script>
</body>
</html>