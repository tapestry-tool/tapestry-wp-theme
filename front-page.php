<?php

/*
 * Register Script with Nonce.
 *
 * @return object null
 */

get_header(); ?>

<div id="primary" class="content-area col-md-12">
    <main id="main" class="site-main post-wrap" role="main">
        <link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/style-front-page.css" media="all" />
        <div class="entry">
            <?php 

                // Show homepage title and content first if there is any content
                $content = trim(get_queried_object()->post_content);
                if (strlen($content)) {
                    echo '<div class="entry-header"><h2 class="entry-title">'. get_the_title() .'</h2></div>';
                    echo '<div class="entry-content">'. apply_filters( 'the_content', $content ) .'</div>';
                }

                // Now, list all tapestries this person has access to
                $tapestries = get_posts(array(
                    'numberposts' => -1,
                    'post_type'   => 'tapestry',
                    'post_status' => 'any',
                    ));
                $count = 0;
                $output = '';
                if (count($tapestries)) {
                    foreach ($tapestries as $tapestry) {
                        if (current_user_can('read', $tapestry->ID) || $tapestry->post_status === 'publish') {
                            $count++;
                            $imageURL = '';
                            if ($count % 3 === 1) {
                                $output .= '<span class="clear"></span>';
                            }
                            $output .= '<div>';

                            $tapestry_data = get_post_meta($tapestry->ID, 'tapestry', true);
                            if ($tapestry_data->rootId) {
                                $nodeMetadata = get_metadata_by_mid('post', $tapestry_data->rootId);
                                $nodeData = get_post_meta($nodeMetadata->meta_value->post_id, 'tapestry_node_data', true);
                                $imageURL = $nodeData->imageURL;
                            }

                            $output .= '<a href="'.get_permalink($tapestry->ID).'" class="thumbnail-container">';
                            $output .= '<span style="background-image:url('.$nodeData->imageURL.');background-color:#'.bin2hex(substr(hash('crc32', $tapestry->post_title), 0, 3)).'"></span>';
                            $output .= '</a>';
                            $output .= '<a href="'.get_permalink($tapestry->ID).'">'.$tapestry->post_title.'</a>';
                            $output .= '<br><small>Created '.date('M jS H:i', strtotime($tapestry->post_date));
                            $output .= '<br>Last modified '.date('M jS H:i', strtotime($tapestry->post_modified));
                            $output .= '<br>Author: '.get_user_by('id', $tapestry->post_author)->data->user_nicename.'</small>';
                            $output .= '<div class="meta">';
                            if ($tapestry->post_status !== 'publish') {
                                $output .= '<small class="label">'.ucfirst($tapestry->post_status).'</small>';
                            }
                            if (current_user_can('delete_post', $tapestry->ID)) {
                                $output .= '<a class="delete-btn" href="'.get_delete_post_link($tapestry->ID).'" onclick="if (!confirm(\'Are you sure you want to delete this tapestry?\')) return false;">Delete</a>';
                            }
                            $output .= '</div>';
                            $output .= '</div>';
                        }
                    }
                }
            ?>
            <div class="entry-header">
                <h2 class="entry-title">Tapestries <small>(<?php echo $count; ?>)</small></h2>
                <?php if (current_user_can('edit_posts')) echo do_shortcode( '[new_tapestry_button]' ); ?>
                <?php if (!is_user_logged_in()) echo '<a class="button" href="'.get_site_url(null, 'wp-login.php?redirect_to='.urlencode(get_site_url())).'">Login</a>'; ?>
            </div>
            <div class="entry-content" id="tapestries-list">
                <?php if (count($tapestries)) echo $output; else echo '<p>No tapestries found. You may need to log in first if you are not already logged in.</p>'; ?>
            </div>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>
