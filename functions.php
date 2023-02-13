<?php
add_action( 'wp_enqueue_scripts', 'tapestry_theme_enqueue_styles' );
function tapestry_theme_enqueue_styles() {
    $parent_style = 'parent-style'; // This is 'twentyfifteen-style' for the Twenty Fifteen theme.
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}

// Save password cookie required to register as a new user
$allow_reg_pwd = "TLEF2020";
$allow_reg_cookie = 'ubc_allow_reg_cookie_is_set_a_ok';
function save_allow_reg_cookie() {
        if ( isset($_POST['allow_registration_pwd']) && $_POST['allow_registration_pwd'] == $allow_reg_pwd ) {
                setcookie('allow_registration_pwd', $allow_reg_cookie);
        }
}
add_action( 'init', 'save_allow_reg_cookie' );

// Change default role to "Author"
// add_filter('pre_option_default_role', 'tapestry_set_default_role');
// function tapestry_set_default_role($default_role) {
//    return 'author';
//}

add_filter('get_the_archive_title', 'tapestry_hide_the_archive_title', 99 );
function tapestry_hide_the_archive_title( $title ) {
	// Skip if the site isn't LTR, this is visual, not functional.
	// Should try to work out an elegant solution that works for both directions.
	if ( is_rtl() ) {
		return $title;
	}
	// Split the title into parts so we can wrap them with spans.
	$title_parts = explode( ': ', $title, 2 );
	// Glue it back together again.
	if ( ! empty( $title_parts[1] ) ) {
		$title = wp_kses(
			$title_parts[1],
			array(
				'span' => array(
					'class' => array(),
				),
			)
		);
		$title = '<span class="screen-reader-text">' . esc_html( $title_parts[0] ) . ': </span>' . $title;
	}
	return $title;
}

// Set first page title on every blog to "Welcome"
add_filter( 'gettext', 'tapestry_multisite_gettext', 10, 3 );
function tapestry_multisite_gettext( $translated, $original, $domain ) {
    if ( $original == "Sample Page" ) {
        $translated = "Welcome";
    }
    return $translated;
}

// Set first page as the homepage in every blog when they get created
// Also remove all widgets from the footer / sidebar
add_action( 'wpmu_new_blog', 'process_extra_field_on_blog_signup', 10, 6 );
function process_extra_field_on_blog_signup( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    switch_to_blog($blog_id);
    // Set homepage
    $homepage = get_page_by_title( __('Sample Page') );   
    if ( $homepage ) {
        update_blog_option( $blog_id, 'page_on_front', $homepage->ID );
        update_blog_option( $blog_id, 'show_on_front', 'page' );
    }
    // Remove widgets
    $sidebars_widgets = get_blog_option( $blog_id, 'sidebars_widgets' );
    $sidebars_widgets['sidebar-1'] = array();
    update_blog_option( $blog_id, 'sidebars_widgets', $sidebars_widgets );
    restore_current_blog();
}

// Activate WordPress Maintenance Mode by uncommenting the line below
// add_action('get_header', 'wp_maintenance_mode');
function wp_maintenance_mode() {
	if (!isset($_GET['test'])){
		if (!current_user_can('edit_themes') || !is_user_logged_in()) {
			wp_die('<h1>We are undergoing regular maintenance at the moment.</h1><br />Please check back soon.');
		}
	}
}

// Add a page to list all users
function tapestry_allusers_admin_menu() {
	add_submenu_page( 'users.php',
		__( 'Users Emails', 'tapestry-tool' ),
		__( 'Users Emails', 'tapestry-tool' ),
		'manage_options',
		'users-table',
		'tapestry_users_table',
		3
	);
}
add_action( 'network_admin_menu', 'tapestry_allusers_admin_menu' );

function tapestry_users_table() {
    $users_args = array(
        'role_in' => ['administrator','editor','author'],
    );

    if (isset($_GET['after'])) {
        $date = preg_replace("([^0-9/] | [^0-9-])", "", $_GET['after']);
        if (strlen($date) > 8) {
            $users_args['date_query'] = array(
                array(
                    'after'     => $date.'00:00:00',
                    'inclusive' => true,
                ),
            );
        }
    }

    $users = [];
    $sites = get_sites();

    foreach ($sites as $site) {
        $users_args2 = array_merge($users_args, array('blog_id'=>$site->blog_id));
        $this_users = get_users($users_args2);
        foreach ($this_users as $user) {
            if (!isset($users[strtolower($user->user_email)])) $users[strtolower($user->user_email)] = [];
            $users[strtolower($user->user_email)]['details'] = $user;
            if (!isset($users[strtolower($user->user_email)][sites])) $users[strtolower($user->user_email)][sites] = [];
            $users[strtolower($user->user_email)][sites][] = $site;
        }
        //echo '<p>'.count($this_users).' Users for Site '.$site->blog_id.'</p>';
    }
    ksort($users);

    ?>
<div class="wrap">
<h1>User Emails (for Tapestry)</h1>
<h3>How to use this tool:</h3>
<p>This page displays all the users who have either an admin, editor, or author role, given specified parameters.</p>
<ul>
  <li>Add <strong>&after=2020-08-01</strong> to view all users after Aug 1, 2020</li>
  <li>Add <strong>&format=csv</strong> to change to commas instead of new lines</li>
  <li>Add <strong>&format=table</strong> to change to table format and include dates</li>
</ul>
<p>&nbsp</p>
    <?php
    echo '<h2>'.count($users).' Users in '.count($sites).' Sites</h2>';
    echo '<hr class="wp-header-end">';
    if (isset($_GET['format']) && $_GET['format'] === 'table') {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Email</th><th>Sites</th></tr></thead><tbody><tr><td>';
    }
    
    foreach ( $users as $email => $user ) {
        echo (isset($_GET['format'])) ? '' : '<p>';
        echo esc_html( $email );
        if (isset($_GET['format']) && $_GET['format'] === 'table') {
           // echo '</td><td><pre>' . print_r($user, true);
           echo '</td><td>' . implode("<br>",array_column($user['sites'],"path"));
        }
        echo (isset($_GET['format']) && $_GET['format'] === 'table') ? '</td></tr><tr><td>' : ( (isset($_GET['format']) && $_GET['format'] === 'csv') ? ', ' : '</p>' );
    }

    if (isset($_GET['format']) && $_GET['format'] === 'table') {
        echo '<td></td></td></tr></table>';
    }

    echo '</div>';

}


// Add a page to list h5p content for a given user ID
function tapestry_user_contributions_admin_menu() {
	add_submenu_page( 'users.php',
		__( 'Users Contributions', 'tapestry-tool' ),
		__( 'Users Contributions', 'tapestry-tool' ),
		'manage_options',
		'users-contributions',
		'tapestry_users_contributions',
		3
	);
}
add_action( 'admin_menu', 'tapestry_user_contributions_admin_menu' );

function tapestry_users_contributions() {

	$h5p_res = null;
	$wp_res = null;
	
	if (isset($_GET['author'])) {
		$author = get_user_by( 'login', $_GET['author'] );
		if ($author) {
			$h5p_url = 'https://ubc.tapestry-tool.com/psyc101/wp-admin/admin-ajax.php?action=h5p_contents&offset=0&limit=100&sortBy=2&sortDir=0&facets[2]='.$author->ID;
		?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function(){
	$.ajax({url: "<?php echo $h5p_url; ?>", success: function(result){
		if (result && result.num) {
			$("#results-h5p").html('');
			result.rows.forEach(function(res){
				$("#results-h5p").append('<li>' + res[0] + '</li>');
			});
		} else {
			$("#results-h5p").html('No H5P contributions found by this user');
		}
	}});
});
</script>
		<?php
		}
	}

    ?>
<div class="wrap">
<h1>User Contributions : <?php echo isset($_GET['author']) ? $_GET['author'] : 'All Users'; ?> </h1>
<div class="update-nag">
<h4 style="margin-top:0;">How to use this tool:</h4>
<p>This page displays all the contributions for the provided author.</p>
<ul>
  <li>Add <strong>&author=johndoe</strong> to view all contributions for the user with username "johndoe"</li>
</ul>
</div>
<h2>Results:</h2>
<?php if (!$author) echo 'Please add a valid author username to the URL by following the instructions above.'; else { ?>
	<h3>H5P Contributions:</h3>
	<div id="results-h5p">No H5P Contributions found by this user.</div>
        <h3>Wordpress Posts Contributions:</h3>
	<div id="results-wp"><a href="<?php echo get_site_url(null, '/wp-admin/edit.php?post_type=post&author='.$author->ID, 'https'); ?>">Click here to view</a></div>
	<h3>Tapestry Contributions:</h3>
	<div id="results-tap"><a href="<?php echo get_site_url(null, '/wp-admin/edit.php?post_type=tapestry&author='.$author->ID, 'https'); ?>">Click here to view</a></div>
	<h3>Tapestry Nodes Contributions:</h3>
	<div id="results-tap-node"><a href="<?php echo get_site_url(null, '/wp-admin/edit.php?post_type=tapestry_node&author='.$author->ID, 'https'); ?>">Click here to view</a></div>
	<?php
	}
echo '</div>';
}
