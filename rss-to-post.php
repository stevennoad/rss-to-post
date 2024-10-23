<?php
/*
Plugin Name: RSS Poster
Plugin URI:  https://github.com/stevennoad/rss-to-post
Description: Fetch a RSS feed and create posts from it.
Version:     2.0.0
Author:      Steve Noad
License:     MIT
Text Domain: rss-to-post
*/

// Enqueue Tailwind CSS
add_action('admin_enqueue_scripts', 'enqueue_tailwind_css');

function enqueue_tailwind_css() {
		wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
}

// Hook for adding admin menu
add_action('admin_menu', 'podcast_rss_poster_menu');

// Function to add the menu item
function podcast_rss_poster_menu() {
		add_menu_page(
				'Podcast RSS Poster',  // Page title
				'Podcast RSS Poster',  // Menu title
				'manage_options',      // Capability
				'podcast-rss-poster',  // Menu slug
				'podcast_rss_poster_settings_page' // Callback function
		);
}

// Callback function to display the settings page
function podcast_rss_poster_settings_page() {
		// Check if a new RSS URL is being saved
		if (isset($_POST['save_rss_url']) && isset($_POST['rss_url'])) {
				$rss_url = esc_url($_POST['rss_url']);
				update_option('podcast_rss_url', $rss_url);  // Save the RSS URL to the database
				echo '<div class="notice notice-success"><p>RSS URL saved successfully.</p></div>';
		}

		// Get the saved RSS URL from the database
		$saved_rss_url = get_option('podcast_rss_url', '');

		?>
		<div class="wrap p-6 bg-white rounded-lg shadow-lg">
				<h1 class="text-2xl font-bold mb-4">Podcast RSS Poster</h1>
				<form method="post" action="" class="mb-4">
						<label for="rss_url" class="block text-sm font-medium text-gray-700">Enter Podcast RSS Feed URL:</label>
						<input type="url" id="rss_url" name="rss_url" value="<?php echo esc_attr($saved_rss_url); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required>
						<br>
						<?php submit_button('Save RSS URL', 'primary', 'save_rss_url', false, ['class' => 'mt-3']); ?>
				</form>

				<?php if (!empty($saved_rss_url)) : ?>
						<h2 class="text-xl font-semibold mt-6">Fetch Saved RSS Feed</h2>
						<form method="post" action="">
								<?php submit_button('Fetch RSS Feed', 'secondary', 'fetch_rss_feed', false, ['class' => 'mt-3']); ?>
						</form>
				<?php endif; ?>

				<?php
				// Fetch the RSS feed when the "Fetch RSS Feed" button is clicked
				if (isset($_POST['fetch_rss_feed']) && !empty($saved_rss_url)) {
						fetch_podcast_rss_feed($saved_rss_url);
				}
				?>
		</div>
		<?php
}


// Function to fetch and display RSS feed items with checkboxes
function fetch_podcast_rss_feed($rss_url) {
		$rss_feed = fetch_feed($rss_url);

		if (is_wp_error($rss_feed)) {
				echo '<div class="notice notice-error"><p>Unable to fetch RSS feed. Please check the URL.</p></div>';
		} else {
				// Parse the feed and display feed items
				echo '<form method="post" action="" class="mt-6">';
				echo '<h2 class="text-xl font-semibold mb-4">Available Podcast Episodes</h2>';

				// Loop through each feed item and display title, description, and checkbox
				foreach ($rss_feed->get_items() as $item) {
						$title = esc_html($item->get_title());
						$description = esc_html($item->get_description());
						$link = esc_url($item->get_link());
						$guid = esc_attr($item->get_id());

						// Extract the image URL from the iTunes tags
						$image_url = get_podcast_image($item);

						echo '<div class="podcast-item border p-4 mb-4 rounded-lg shadow-md flex items-start">';
						echo '<div class="flex-shrink-0 mr-4">'; // Container for the image with margin
						if ($image_url) {
								echo '<img src="' . esc_url($image_url) . '" alt="Podcast Image" class="max-w-xs rounded-md">'; // Image styling
						}
						echo '</div>';
						echo '<div>'; // Container for the text content
						echo '<input type="checkbox" name="podcast_items[]" value="' . $guid . '" class="mr-2 align-middle"> '; // Checkbox
						echo '<strong class="text-lg">' . $title . '</strong><br>'; // Title
						echo '<p class="text-gray-700 my-2">' . $description . '</p>'; // Description
						echo '<a href="' . $link . '" target="_blank" class="text-blue-600 underline">Read more</a>'; // Read more link
						echo '</div>'; // Close text content container
						echo '</div>'; // Close podcast item container

				}

				// Submit button for importing selected items
				submit_button('Import Selected Episodes', 'primary', 'import_podcast_episodes', false, ['class' => 'mt-4']);
				echo '</form>';
		}
}

// Helper function to extract iTunes podcast image
function get_podcast_image($item) {
		$itunesImageTags = $item->get_item_tags('http://www.itunes.com/dtds/podcast-1.0.dtd', 'image');
		return isset($itunesImageTags[0]['attribs']['']['href']) ? esc_url($itunesImageTags[0]['attribs']['']['href']) : '';
}

// Handle importing selected podcast episodes with metadata
function podcast_rss_poster_import_selected_items() {
		if (isset($_POST['import_podcast_episodes']) && isset($_POST['podcast_items'])) {
				$selected_items = $_POST['podcast_items']; // Array of GUIDs (unique identifiers)

				foreach ($selected_items as $guid) {
						if (!podcast_rss_poster_is_item_imported($guid)) {
								$rss_feed = fetch_feed(get_option('podcast_rss_url'));

								foreach ($rss_feed->get_items() as $item) {
										if ($item->get_id() === $guid) {
												$title = $item->get_title();
												$content = $item->get_description();
												$link = $item->get_link();
												$pub_date = $item->get_date();
												$enclosure = $item->get_enclosure();
												$audio_url = $enclosure ? esc_url($enclosure->get_link()) : '';
												$duration = $enclosure ? esc_html($enclosure->get_duration()) : '';

												// Extract the podcast image
												$image_url = get_podcast_image($item);

												// Create a new podcast post with metadata
												$post_data = array(
														'post_title'   => wp_strip_all_tags($title),
														'post_content' => $content,
														'post_status'  => 'publish',
														'post_type'    => 'post',  // Use custom post type 'podcast'
														'meta_input'   => array(
																'_podcast_guid'     => $guid,
																'_podcast_link'     => $link,
																'_podcast_pub_date' => $pub_date,
																'_podcast_audio_url'=> $audio_url,
																'_podcast_duration' => $duration,
														)
												);

												$post_id = wp_insert_post($post_data);

												// Set the image as the post thumbnail if available
												if (!empty($image_url)) {
														attach_image_to_post($image_url, $post_id);
												}

												// Set the category as season
												$season = get_podcast_season($item);
												if (!empty($season)) {
														$category = get_category_by_slug($season);
														if ($category) {
																wp_set_post_categories($post_id, array($category->term_id));
														} else {
																// Create the category if it doesn't exist
																$new_category_id = wp_create_category($season);
																wp_set_post_categories($post_id, array($new_category_id));
														}
												}

												echo '<div class="notice notice-success"><p>Episode "' . esc_html($title) . '" imported successfully.</p></div>';
										}
								}
						} else {
								echo '<div class="notice notice-info"><p>Episode already imported. Skipping...</p></div>';
						}
				}
		}
}

// Helper function to extract the podcast season
function get_podcast_season($item) {
		// Assuming season info is stored in a custom iTunes tag
		$itunesSeasonTags = $item->get_item_tags('http://www.itunes.com/dtds/podcast-1.0.dtd', 'season');

		// Check if the tag exists and return the season value
		if (isset($itunesSeasonTags[0]['data'])) {
				return sanitize_title($itunesSeasonTags[0]['data']); // Use sanitize_title for a safe slug
		}

		return null; // Return null if no season information is found
}


// Helper function to attach an image to a post
function attach_image_to_post($image_url, $post_id) {
		$image_data = @file_get_contents($image_url);

		if ($image_data === false) {
				error_log('Failed to fetch image from: ' . $image_url);
				return;
		}

		// Prepare the upload directory
		$upload_dir = wp_upload_dir();
		$filename = basename($image_url);
		$upload_path = $upload_dir['path'] . '/' . $filename;

		// Save the image locally
		file_put_contents($upload_path, $image_data);

		// Prepare the attachment
		$attachment = array(
				'post_mime_type' => 'image/jpeg', // Adjust MIME type based on the image
				'post_title'     => sanitize_file_name($filename),
				'post_content'   => '',
				'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment($attachment, $upload_path, $post_id);

		// Generate metadata for the attachment and set it as the post thumbnail
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
		wp_update_attachment_metadata($attachment_id, $attach_data);
		set_post_thumbnail($post_id, $attachment_id);
}


// Helper function to check if a podcast item has already been imported
function podcast_rss_poster_is_item_imported($guid) {
		// Query posts to check if the GUID is already stored as a meta field
		$existing_post = new WP_Query(array(
				'meta_key'   => '_podcast_guid',
				'meta_value' => $guid,
				'post_type'  => 'post',
				'post_status'=> 'any'
		));

		return $existing_post->have_posts();
}

// Hook to handle post import process when the import button is clicked
add_action('admin_init', 'podcast_rss_poster_import_selected_items');

// Wordpress auto updater
require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/stevennoad/rss-to-post/',
	__FILE__,
	'rss-to-post'
);

// Set the branch that contains the stable release.
$myUpdateChecker->getVcsApi()->enableReleaseAssets();
