<?php

/*
Plugin Name: Video Analytics for Cloudflare Stream
Description: Video Analytics for Cloudflare Stream
Version:     1.2
Author:      <a href="https://cfpowertools.com">cfpowertools.com</a>
Text Domain: video-analytics-for-cloudflare-stream
*/

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

include 'csa-options.php';
function vafcs_set_plugin_meta( $links, $file )
{
    $plugin = plugin_basename( __FILE__ );
    // create link
    if ( $file == $plugin ) {
        return array_merge( $links, array( sprintf( '<a href="admin.php?page=cfsa-plugin-2">' . __( 'Settings', 'video-analytics-for-cloudflare-stream') . '</a>', $plugin, __( 'Settings', 'video-analytics-for-cloudflare-stream') ) ) );
    }
    return $links;
}

add_filter(
    'plugin_row_meta',
    'vafcs_set_plugin_meta',
    10,
    2
);

add_action( 'admin_notices', 'vafcs_admin_notice');

function vafcs_admin_notice()
{
    global $pagenow;
    $page_param = '';
    if (isset($_GET['page']))
    {
        $page_param = $_GET['page'];
    }
    if ( $pagenow == 'admin.php' && ($page_param == 'cfsa-plugin' or $page_param == 'cfsa-plugin-2')  ){
		$settings_array = get_option( 'csa_settings' );
   		if (!$settings_array) {
        	echo '<div class="error"><p>';
        	_e('Please enter your Cloudflare account details on the settings page', 'video-analytics-for-cloudflare-stream');
        	echo '</p></div>';   		}
	}
}

function vafcs_toHHMMSS( $seconds )
{
    $t = round( $seconds );
    return sprintf(
        '%02d:%02d:%02d',
        $t / 3600,
        $t / 60 % 60,
        $t % 60
    );
}

function vafcs_display()
{
    $settings_array = get_option( 'csa_settings' );
    // load global settings
    $title = __( 'Video Analytics for Cloudflare Stream', 'video-analytics-for-cloudflare-stream' );
    echo  '<h1>' . $title . '</h1>' ;
    
         $display_days = get_option( 'display_days' );
        // choose which one is selected. $settings_array['displaydays'];
        ?>
	<form action="<?php 
        echo  esc_url( admin_url( 'admin-post.php' ) ) ;
        ?>" method="post">
	<input type="hidden" name="action" value="vafcs_change_display_days">
	<label style="display: inline-block;"><?php 
        echo  __( 'For the past ', 'video-analytics-for-cloudflare-stream' ) ;
        ?></label>
    <div style="display: inline-block;" >
	<select id="displaydays" name="displaydays" onchange="this.form.submit()">
     <option value="1"<?php 
        if ( $display_days == '1' ) {
            echo  ' selected="selected"' ;
        }
        ?>>1 day</option>
     <option value="7"<?php 
        if ( $display_days == '7' ) {
            echo  ' selected="selected"' ;
        }
        ?>>7 days</option>
	 <option value="30"<?php 
        if ( $display_days == '30' ) {
            echo  ' selected="selected"' ;
        }
        ?>>30 days</option>
     </select>
	</div>
	 </form>
	<?php 
        echo  '<br>' ;
    
    $timefrom = substr_replace( gmdate( 'c', strtotime( "-" . $display_days . " day" ) ), 'Z', -6 );
    $json = vafcs_api_call( 'listallvideos', 0, $timefrom );

    if (isset($_GET['pagenum'])){
        $pagenumreq = sanitize_text_field($_GET['pagenum'] ); // input escaped - done
    }
    $pagenum = ( isset( $pagenumreq ) ? absint( $pagenumreq ) : 1 ); 
    if ($pagenum==0){
		$pagenum=1;
	}
    $nb_elem_per_page = ( isset( $settings_array['cf_items_pp'] ) ? $settings_array['cf_items_pp'] : 5 );
    $data = (array) $json['result'];
    $number_of_pages = ceil( count( $data ) / $nb_elem_per_page );
    echo  "<table class='wp-list-table widefat fixed posts'><tr>" ;
	echo  "<th><b>" . __( 'Name of Video', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
	echo  "<th><b>" . __( 'Time uploaded (UTC)', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
    echo  "<th><b>" . __( 'Number of views', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
    echo  "<th><b>" . __( 'Total duration', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
    echo  "<th><b>" . __( 'Average view time', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
    echo  "<th><b>" . __( 'Average view %', 'video-analytics-for-cloudflare-stream' ) . "</b></th>" ;
   
    echo  "</tr>" ;
    
    foreach ( array_slice( $data, ($pagenum - 1) * $nb_elem_per_page, $nb_elem_per_page ) as $video ) {
		echo  "<tr>" ;
		$analytics = vafcs_api_call( 'viewanalytics', $video['uid'], $timefrom );
       		if ($video['readyToStream'] == false){ 
                echo "<th>" . $video['uid'] . "</th>"; // name 
                if ($video['status']['step']){
                    $status_msg =  $video['status']['step'] . " -" . $video['status']['pctComplete'] . "%";
                } else {
                    $status_msg = "Uploading"; // 
                }
				echo "<th>" . $status_msg . "</th>"; // time uploaded
                echo "<th>" . __( 'N/A', 'video-analytics-for-cloudflare-stream' ) . "</th>"; // total views
                echo "<th>" . __( 'N/A', 'video-analytics-for-cloudflare-stream' ) . "</th>"; // duration
	
			} else{
                $createdDate = date('Y-m-d', strtotime(substr($video['created'],0,10)));
				// check $timefrom or created whichever is the earliest
                if ($createdDate >= $timefrom){
                    $timefrom_modified = substr($video['created'],0,19) . "Z";
                } else{
					$timefrom_modified = $timefrom;
				}
                $video_page = $video['uid'];
                echo  "<th><a href='/wp-admin/admin.php?page=cfsa-plugin-view&uid=" . $video_page . "&timefrom=" . $timefrom_modified . "'>" . $video['meta']['name'] . "</a></th>"; // name
			    echo  "<th>" . date( 'd-F-Y H:i', strtotime( $video['created'] ) ) . "</th>"; // time uploaded
                echo  "<th>" . $analytics['result']['totals']['totalImpressions'] . "</th>"; // total views
                echo  "<th>" . vafcs_toHHMMSS( $video['duration'] ) . "</th>"; // duration
            }
            $av_view_time=0;
            $pcview=0;
            if ($analytics['result']['totals']['totalImpressions'] != 0){
                $av_view_time = vafcs_toHHMMSS( intval( $analytics['result']['totals']['totalTimeViewedMs'] * 0.001 / $analytics['result']['totals']['totalImpressions'] ) );
                $pcview = round(100*(($analytics['result']['totals']['totalTimeViewedMs']/$analytics['result']['totals']['totalImpressions'])/ $video['duration']*0.001),2);

            }
            echo  "<th>" . $av_view_time . "</th>" ;
            echo  "<th>";
			if (!is_nan($pcview)){ echo $pcview . "%"; }  
			echo "</th>" ;
        
        echo  "</tr>" ;
    }
    echo  "</table>" ;
    
    if ( count( $data ) > $nb_elem_per_page ) {
        $page_links = paginate_links( array(
            'base'      => add_query_arg( 'pagenum', '%#%' ),
            'format'    => '',
            'prev_text' => __( '&laquo;', 'video-analytics-for-cloudflare-stream' ),
            'next_text' => __( '&raquo;', 'video-analytics-for-cloudflare-stream' ),
            'total'     => $number_of_pages,
            'current'   => $pagenum,
        ) );
        if ( $page_links ) {
            echo  '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>' ;
        }
    }

    vafcs_display_premium_ad();

}

function vafcs_display_premium_ad()
{
    echo "<center><p><h2>If you like this, check out our premium offering at:</p><p><a href='https://cfpowertools.com' target='_blank'>cfpowertools.com</a></h2></p>";
    echo "<p><a href='https://cfpowertools.com' target='_blank'><img src='" . plugin_dir_url( __FILE__ ). "img/logo.png'  width=200></a></p>";
    echo "<p><h3>The complete solution to integrate Cloudflare Stream and Wordpress<br>";
    echo "Import videos, restrict viewing to your domains & get analytics in one single plugin</p></h3>";
    echo "<p>Prevent downloads</p><p>Easily import videos</p><p>Manage from WordPress dashboard</p><p>Insert video with shortcodes</p><p>Analytics incl CSV export and custom dates</p><p>Updates &amp; Support</p>";
    echo "</p></center>";
}


    add_action( 'admin_post_vafcs_change_display_days', 'vafcs_change_display_days' );
    function vafcs_change_display_days()
    {
        //$settings_array = get_option( 'csa_settings' );
        $url = parse_url( wp_get_referer() );
        parse_str( $url['query'], $path );
        $pagenum = $path['pagenum'];
        $display_days = sanitize_text_field($_POST['displaydays']); // escaped - done
        update_option( 'display_days', $display_days );
        wp_redirect( admin_url( 'admin.php?page=cfsa-plugin&pagenum=' . $pagenum ) );
        exit;
	}
?>