    <?php
    session_start();
    require_once 'config.php';

    if (!isset($_SESSION['user_id'])) {
        redirect('index.php');
    }

    // Database connection
    $conn = createDatabaseConnection();

    // Fetch posts with user information
    $query = "SELECT p.post_id, p.title, p.content, p.location, p.image_path, p.created_at, 
                    u.username, u.profile_picture, 
                    c.name AS category_name 
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            JOIN categories c ON p.category_id = c.category_id
            ORDER BY p.created_at DESC";

    $result = $conn->query($query);

    // Fetch categories for dropdown
    $categories_query = "SELECT * FROM categories";
    $categories_result = $conn->query($categories_query);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Travel Blog</title>
        <link rel="stylesheet" href="home.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
        <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    </head>
    <body>
    <div class="sidebar">
    <div class="logo">Wanderlust</div>
    <ul>
        <li><a href="home.php"><i>🏠</i>Home Page</a></li>
        <li><a href="search.php"><i>🔍</i>Search</a></li>
        <li><a href="bookmark_post.php"><i>🔖</i>Bookmark</a></li>
        <li><a href="#" onclick="toggleMap()"><i>🗺️</i>Map</a></li>
        <div class="profile">
            <a href="profile.php"><i>👤</i>Profile</a>
        </div>
    </ul>
    <div>
        <button class="logout-button" onclick="logout()">Logout</button>
    </div>
</div>

<!-- Map Container in Sidebar -->
<div id="map-container" style="display:none; margin-top: 20px;">
    <button onclick="closeMap()" style="position: absolute; top: 10px; right: 10px; background-color: #ff4d4d; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s ease, transform 0.3s ease;">
        Close Map
    </button>
    <div id="map" style="width:100%; height:300px;"></div>
</div>

<div class="main-content">
    <div class="top-bar">
        <button>Recommendation</button>
        <button>Following</button>
    </div>

    <div id="post-section">
        <div class="post-box">
            <form id="create-post-form" method="POST" enctype="multipart/form-data">
                <input type="text" name="title" placeholder="Post Title" required>
                <textarea name="content" placeholder="Describe your travel experience..." required></textarea>

                <label for="category_id">Category/Interest:</label>
                <select name="category_id" id="category_id" required>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="hidden" id="location" name="location">

                <div class="actions">
                    <div>
                        <label for="image-upload" style="cursor: pointer;">📷</label>
                        <input type="file" id="image-upload" name="image[]" style="display: none;" accept="image/*" multiple>

                        <button type="button" onclick="showLocationPicker()">📍</button>
                    </div>
                    <button type="submit">Post</button>
                </div>
            </form>
        </div>

        <!-- Location Picker Map -->
        <div id="location-picker-map"></div>

        <!-- Posts Container -->
        <div class="posts">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="post" id="post-<?php echo $row['post_id']; ?>">
                    <div class="post-header">
                        <div class="author-info">
                            <img 
                                src="<?php echo htmlspecialchars($row['profile_picture'] ?: 'default-avatar.png'); ?>" 
                                alt="<?php echo htmlspecialchars($row['username']); ?>'s avatar" 
                                class="author-avatar"
                            >
                            <div class="author-details">
                                <a href="profile.php?username=<?php echo htmlspecialchars($row['username']); ?>" class="author-name">
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </a>
                                <small class="post-date">
                                    <?php 
                                    $postDate = new DateTime($row['created_at']);
                                    echo $postDate->format('M d, Y H:i');
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($row['image_path'])): ?>
                        <img 
                            src="<?php echo htmlspecialchars($row['image_path']); ?>" 
                            alt="Post Image" 
                            class="post-image"
                            onerror="this.style.display='none'">
                    <?php endif; ?>

                    <div class="post-content">
                        <div class="title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div class="content">
                            <?php 
                            $content = $row['content'];
                            echo strlen($content) > 200 ? substr($content, 0, 200) . '...' : $content; 
                            ?>
                        </div>
                        <div class="category">
                            Category: <?php echo htmlspecialchars($row['category_name']); ?>
                        </div>
                        <?php if (!empty($row['location'])): ?>
                            <div class="location">
                                Location: <?php echo htmlspecialchars($row['location']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="post-actions">
                        <div class="action-buttons">
                            <button onclick="likePost(<?php echo $row['post_id']; ?>)">
                                👍 <span class="like-count">0</span>
                            </button>
                            <button onclick="commentPost(<?php echo $row['post_id']; ?>)">
                                💬 <span class="comment-count">0</span>
                            </button>
                            <button onclick="bookmarkPost(<?php echo $row['post_id']; ?>)">
                                🔖 Bookmark
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
        <script>
            // Image Upload Preview
            document.getElementById('image-upload').addEventListener('change', function(event) {
                const existingPreviews = document.querySelectorAll('.image-preview');
                existingPreviews.forEach(preview => preview.remove());
                
                Array.from(event.target.files).forEach(file => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewContainer = document.createElement('div');
                        previewContainer.className = 'image-preview';
                        previewContainer.innerHTML = `<img src="${e.target.result}" style="max-width:200px; max-height:200px; margin:10px; display:inline-block;">`;
                        
                        const actionsDiv = document.querySelector('.actions');
                        actionsDiv.parentNode.insertBefore(previewContainer, actionsDiv);
                    }
                    
                    reader.readAsDataURL(file);
                });
            });

            // Location Picker
            function showLocationPicker() {
                const mapContainer = document.getElementById('location-picker-map');
                mapContainer.style.display = 'block';
                mapContainer.style.height = '400px';
                mapContainer.style.width = '100%';
                
                setTimeout(initLocationPicker, 100);
            }

            function initLocationPicker() {
                const map = L.map('location-picker-map').setView([41.9973, 21.4280], 8);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                const marker = L.marker([41.9973, 21.4280], {
                    draggable: true
                }).addTo(map);

                const confirmButton = document.createElement('button');
                confirmButton.textContent = 'Confirm Location';
                confirmButton.style.backgroundColor = 'green';
                confirmButton.style.color = 'white';
                confirmButton.style.padding = '10px';
                confirmButton.style.border = 'none';
                confirmButton.style.borderRadius = '5px';
                confirmButton.style.cursor = 'pointer';

                confirmButton.onclick = function() {
                    const currentLocation = marker.getLatLng();
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${currentLocation.lat}&lon=${currentLocation.lng}`)
                        .then(response => response.json())
                        .then(data => {
                            const locationName = data.display_name || `${currentLocation.lat.toFixed(4)}, ${currentLocation.lng.toFixed(4)}`;
                            
                            const existingLocationPreview = document.querySelector('.location-preview');
                            if (existingLocationPreview) {
                                existingLocationPreview.remove();
                            }

                            const locationPreview = document.createElement('div');
                            locationPreview.className = 'location-preview';
                            locationPreview.innerHTML = `<strong>Confirmed Location:</strong> ${locationName}`;
                            locationPreview.style.margin = '10px 0';
                            
                            const actionsDiv = document.querySelector('.actions');
                            actionsDiv.parentNode.insertBefore(locationPreview, actionsDiv);

                            document.getElementById('location').value = locationName;
                            document.getElementById('location-picker-map').style.display = 'none';
                        });
                };

                const buttonContainer = document.createElement('div');
                buttonContainer.style.position = 'absolute';
                buttonContainer.style.bottom = '10px';
                buttonContainer.style.left = '50%';
                buttonContainer.style.transform = 'translateX(-50%)';
                buttonContainer.style.zIndex = '1000';
                
                buttonContainer.appendChild(confirmButton);
                document.getElementById('location-picker-map').appendChild(buttonContainer);

                map.on('click', function(e) {
                    marker.setLatLng(e.latlng);
                });
            }

            // Map Functions
           // Map Toggle Functions
function toggleMap() {
    const mapContainer = document.getElementById('map-container');
    if (mapContainer.style.display === 'none') {
        mapContainer.style.display = 'block';
        initMap(); // Initialize the map when it's shown
    } else {
        mapContainer.style.display = 'none';
        destroyMap(); // Destroy map when it's hidden to prevent it from running in the background
    }
}

function closeMap() {
    document.getElementById('map-container').style.display = 'none';
    destroyMap(); // Optionally destroy the map when closed to release resources
}

// Global variable to hold the map instance
let map;

function initMap() {
    if (!map) {
        const location = { lat: 41.9981, lng: 21.4254 };
        map = L.map('map').setView([location.lat, location.lng], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.marker([location.lat, location.lng]).addTo(map);
    }
}

// Function to destroy the map instance when it is closed
function destroyMap() {
    if (map) {
        map.remove(); // This removes the map instance and all its layers
        map = null; // Set the map variable to null to ensure it's re-initialized next time
    }
}

function logout() {
    window.location.href = 'logout.php';
}

            // Post Submission
            document.getElementById('create-post-form').addEventListener('submit', async (e) => { e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        try {
            const response = await fetch('post.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                const postSection = document.querySelector('.posts');
                const newPost = document.createElement('div');
                newPost.classList.add('post');
                
                // Create post HTML with image handling
                let postHTML = `
                    <div>
                        <div class="author">You</div>
                        ${result.data.image_path ? `<img src="${result.data.image_path}" alt="Post Image" class="post-image">` : ''}
                        <div class="title"><strong>${result.data.title}</strong></div>
                        <div class="content">${result.data.content}</div>
                        <div class="category">Category: ${result.data.category}</div>
                        <div class="location">Location: ${result.data.location}</div>
                    </div>
                    <div class="post-actions">
                        <button onclick="likePost(${result.data.post_id})">👍 Like</button>
                        <button onclick="commentPost(${result.data.post_id})">💬 Comment</button>
                        <button onclick="bookmarkPost(${result.data.post_id})">🔖 Bookmark</button>
                    </div>
                `;

                newPost.innerHTML = postHTML;
                postSection.prepend(newPost);
                form.reset();
                
                // Clear image previews
                const previews = document.querySelectorAll('.image-preview, .location-preview');
                previews.forEach(preview => preview.remove());
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while creating the post.');
        }
    });


            // Placeholder functions for post interactions
            function likePost(postId) {
                console.log('Like post', postId);
            }

            function commentPost(postId) {
                console.log('Comment on post', postId);
            }

            function bookmarkPost(postId) {
                console.log('Bookmark post', postId);
            }

        </script>
    </body>
    </html>

    <?php
    // Close database connection
    $conn->close();
    ?>
