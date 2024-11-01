<?php

// adds an api call function
function vafcs_api_call( $calltype, $uid, $timefrom ) {

  $settings_array=get_option( 'csa_settings' );
    $cfurl='';
    $cf_ac_id = $settings_array['cf_account_id'];

  if ($calltype == 'getaccountid'){
    $cfurl='https://api.cloudflare.com/client/v4/accounts?page=1&per_page=20&direction=desc'; // what is the per page here??
  }
  elseif ($calltype == 'listallvideos'){
    $cfurl='https://api.cloudflare.com/client/v4/accounts/' . $cf_ac_id . '/stream';
  }
  elseif ($calltype == 'viewanalytics'){
    $cfurl='https://api.cloudflare.com/client/v4/accounts/' . $cf_ac_id . '/media/analytics/views?metrics=totalImpressions,totalTimeViewedMs&dimensions=videoId&filters=videoId==' . $uid . '&since=' . $timefrom;
    //. '&until=' . $timeto . '&limit=' . $timestep
  }
  elseif ($calltype == 'getembedcode'){
    $cfurl='https://api.cloudflare.com/client/v4/accounts/' . $cf_ac_id  . '/stream/' . $uid. '/embed';
   
  }elseif ($calltype == 'getvideo'){
    $cfurl='https://api.cloudflare.com/client/v4/accounts/' . $cf_ac_id . '/stream/' . $uid;

  }
	
	
  $headers = array( 
      'X-Auth-Email' => $settings_array['cf_email'],
      'X-Auth-Key' => $settings_array['cf_api_key'],
      'Content-type' => 'application/json'
      );

  global $wp_version;
  $args = array(
      'timeout'     => 30,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking'    => true,
      'headers'     => $headers,
  ); 

  $response = wp_remote_get( $cfurl, $args );
  if ( is_array( $response ) ) {
    //$header = $response['headers']; // array of http header lines
    $body = $response['body'];
    if ( $calltype != 'getembedcode'){
        // need to check for valid answer
        return json_decode($body,true); // return the repsonse

    }else{
        // need to check for valid answer
        return $body;
        
    }
  
  }

}


function vafcs_api_post( $calltype, $url, $video_name ) {

    $settings_array=get_option( 'csa_settings' );
    $cfurl='';
     
    $post_vars = "{\"url\":\"" . $url . "\",\"meta\":{\"name\":\"" . $video_name . "\"}}";
        
    $headers = array( 
        'X-Auth-Email' => $settings_array['cf_email'],
        'X-Auth-Key' => $settings_array['cf_api_key'],
		'Content-Type' => 'application/json'
        );
  
    global $wp_version;
    $args = array(
        'timeout'     => 30,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => $headers,
        'body' => $post_vars,
		'method' => 'POST'
    ); 

    $response = wp_remote_request( $cfurl, $args );
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
     } else {
        if ( is_array( $response ) ) {
            //$header = $response['headers']; // array of http header lines
            $body = $response['body'];
            return json_decode($body,true); // return the repsonse
          }
     }
  
  }


class Video_Analytics_Cloudflare_Stream
{
    //Holds the values to be used in the fields callbacks
    private $options;

    //Start up
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'update_option_csa_settings',  array( $this,'vafcs_options_after_save'),10,2 );
		
    }

    //Add options page
    public function add_plugin_page()
    {
		add_menu_page('Video Analytics for Cloudflare', 'Video Analytics for Cloudflare', 'manage_options', 'cfsa-plugin', 'vafcs_display', 'dashicons-cloud' );
		add_submenu_page(
			'cfsa-plugin',
            __('Video Analytics for Cloudflare Stream','video-analytics-for-cloudflare-stream' ), 
            'Settings', 
            'manage_options', 
            'cfsa-plugin-2', 
            array( $this, 'create_admin_page' )
        );
    
        add_submenu_page( // Viewing of individual video
            'options.php', 
            __('Cloudflare Stream Video','video-analytics-for-cloudflare-stream' ), 
            '',
            'manage_options',
            'cfsa-plugin-view',
            array( $this, 'create_view_page' )
        );
 
    }
    
    
    //View page callback
    public function create_view_page()
    {
        ?>
        <div class="wrap">
            <?php
            if (isset($_GET['uid']) && isset($_GET['timefrom']) ) {
                $uid = sanitize_text_field($_GET['uid']); // sanitized
                $timefrom = sanitize_text_field($_GET['timefrom']); // sanitized
                $json = vafcs_api_call( 'getvideo', $uid, $timefrom); 
                // fail with error msg if UID invalid
                if ($json['result'] == NULL){
					echo __('Video UID Error','video-analytics-for-cloudflare-stream' );
					http_response_code(404);
					die();
                }
                // fail with error msg if timefrom invalid
                $chktime = preg_match('#^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#',substr($timefrom, 11, 8));
                if (!checkdate (substr($timefrom, 5, 6) , substr($timefrom, 8, 9), substr($timefrom, 0, 3)) || strlen($timefrom) != 20  || substr($timefrom, -10, 1) != 'T' || substr($timefrom, -1, 1) != 'Z'  || $chktime == FALSE){
                    echo __('Date error','video-analytics-for-cloudflare-stream' );
					http_response_code(404);
					die();
                }
                // fail if timefrom not between now and $video['created']
                $video = (array)$json['result'];
                $chkDate = date('Y-m-d', strtotime(substr($timefrom,0,10)));
                $startDate = date('Y-m-d', strtotime(substr($video['created'],0,10)));
                $currentDate = date('Y-m-d');
                $currentDate = date('Y-m-d', strtotime($currentDate));
                if (!($chkDate >= $startDate) || !($chkDate <= $currentDate)){
                    echo __('Date out of range','video-analytics-for-cloudflare-stream' );
					http_response_code(404);
					die();
                }
                echo '<div><h2><a href=' . $video['preview'] . ' target="_blank">' . $video['meta']['name'] . '</a></h2></div>'; 
                echo '<img src="https://videodelivery.net/' . $uid .'/thumbnails/thumbnail.jpg?time=5s&height=300">'; // Static thumbnail for free version
                // add analytics graph here - needs sanitized timefrom
                echo '<br><br>';
                echo  "<b>" . __('Time Uploaded (UTC): ', 'video-analytics-for-cloudflare-stream') . "</b>" . date( 'd-F-Y H:i', strtotime( $video['created'] ) ) . "<br>"; 
                echo  "<b>" . __('Video duration: ', 'video-analytics-for-cloudflare-stream') . "</b>" . vafcs_toHHMMSS( $video['duration'] ) . "<br><br>";
                echo __('Copy the code below and paste it into your post to embed the video:','video-analytics-for-cloudflare-stream' );
                echo "<br><a href='https://developers.cloudflare.com/stream/video-playback/player-api/' target='_blank'>" . __('See the Cloudflare docs for more options', 'video-analytics-for-cloudflare-stream' ) . "</a>";
                echo '<br><br>';
                $embedcode = vafcs_api_call( 'getembedcode', $uid, $timefrom );
                echo '<span><code>' . esc_html($embedcode) . '</code></span><br><br>';
                
            ?>

        </div>
        <?php
    }}

    //Options page callback
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'csa_settings' );
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'my_option_group' );
                do_settings_sections( 'csa-settings' );
                submit_button();
                vafcs_display_premium_ad();
            ?>
            </form>
        </div>
        <?php
    }

    //Register and add settings
    public function page_init()
    {        
        register_setting(
            'my_option_group', // Option group
            'csa_settings', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'Video Analytics for Cloudflare Stream Settings', // i8n string
            array( $this, 'print_section_info' ), // Callback
            'csa-settings' // Page
        );  

        add_settings_field(
            'cf_email', 
            'Email used to login to Cloudflare', // i8n string
            array( $this, 'cf_email_callback' ), 
            'csa-settings', 
            'setting_section_id'
        );      
        
        add_settings_field(
            'cf_api_key', 
            'Cloudflare API key', // i8n string
            array( $this, 'cf_api_key_callback' ), 
            'csa-settings', 
            'setting_section_id'
        );  
        
        add_settings_field(
            'cf_account_id', 
            'Cloudflare Account ID', // i8n string
            array( $this, 'cf_account_id_callback' ), 
            'csa-settings', 
            'setting_section_id'
        );  

        add_settings_field(
            'cf_items_pp', 
            'Items per page', // i8n string
            array( $this, 'cf_items_pp_callback' ), 
            'csa-settings', 
            'setting_section_id'
        ); 
/*
        add_settings_field(
            'cf_thumbnail_type', 
            'Thumbnail type',  // i8n string
            array( $this, 'cf_thumbnail_type_callback' ), 
            'csa-settings', 
            'setting_section_id'
        );
        */
    }

    //Sanitize each setting field as needed
    public function sanitize( $input ) {
        
        $new_input = array();
        
        if( isset( $input['cf_email'] ) )
            $new_input['cf_email'] = sanitize_text_field( $input['cf_email'] );
            
        if( isset( $input['cf_api_key'] ) )
            $new_input['cf_api_key'] = sanitize_text_field( $input['cf_api_key'] );
        
        if( isset( $input['cf_account_id'] ) )
            $new_input['cf_account_id'] = sanitize_text_field( $input['cf_account_id'] );
            
        if( isset( $input['cf_items_pp'] ) )
            $new_input['cf_items_pp'] = sanitize_text_field( $input['cf_items_pp'] );
            
        return $new_input;
    }

    //Print the Section text
    public function print_section_info(){
        _e('Enter your Cloudflare account details here. You need a paid account to use Cloudflare Stream.', 'video-analytics-for-cloudflare-stream');
    }

    //Get the settings option array and print one of its values
    public function cf_email_callback(){
        printf(
            '<input type="text" id="cf_email" name="csa_settings[cf_email]" value="%s" />',
            isset( $this->options['cf_email'] ) ? esc_attr( $this->options['cf_email']) : ''
        );
    }
    
    public function cf_api_key_callback(){
        printf(
            '<input type="text" id="cf_api_key" name="csa_settings[cf_api_key]" value="%s" />',
            isset( $this->options['cf_api_key'] ) ? esc_attr( $this->options['cf_api_key']) : ''
        );
    }
    
    public function cf_account_id_callback(){
        printf(
            '<input type="text" id="cf_account_id" name="csa_settings[cf_account_id]" value="%s" />',
            isset( $this->options['cf_account_id'] ) ? esc_attr( $this->options['cf_account_id']) : ''
        );
    }
    
    public function cf_items_pp_callback(){
            printf(
                '<input type="text" id="cf_items_pp" name="csa_settings[cf_items_pp]" value="%s" />',
                isset( $this->options['cf_items_pp'] ) ? esc_attr( $this->options['cf_items_pp']) : ''
            );
    }
    
/*    public function cf_thumbnail_type_callback(){
        printf(
            // radio buttons for static vs first 5 sec gif here
        );
    } */

    
}

if( is_admin() ){
    $my_settings_page = new Video_Analytics_Cloudflare_Stream();
}
