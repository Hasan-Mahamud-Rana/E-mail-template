<?php
/*
Plugin Name: eCards Lite
Plugin URI: https://getbutterfly.com/wordpress-plugins/wordpress-ecards-plugin/
Description: eCards is a plugin used to send electronic cards to friends. It can be implemented in a page, a post or the sidebar. eCards makes it quick and easy for you to send an eCard in 3 easy steps. Just choose your favorite eCard, add your personal message, and send it to any email address. Use preset images, upload your own or select from your Dropbox folder.
Author: Ciprian Popescu
Author URI: https://getbutterfly.com/
Version: 3.1
eCards Lite
Copyright (C) 2011, 2012, 2013, 2014, 2015 Ciprian Popescu (getbutterfly@gmail.com)
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

//
define('ECARDS_PLUGIN_URL', WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)));
define('ECARDS_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)));
define('ECARDS_VERSION', '3.1');

// plugin initialization
function ecards_init() {
	load_plugin_textdomain('ecards', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'ecards_init');

// apply content shortcode fix
if(get_option('ecard_shortcode_fix') === 'on')
    add_filter('the_content', 'do_shortcode');

include ECARDS_PLUGIN_PATH . '/includes/functions.php';
include ECARDS_PLUGIN_PATH . '/includes/page-options.php';

function eCardsInstall() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'ecards_stats';
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			`date` date NOT NULL,
			`sent` mediumint(9) NOT NULL,
            UNIQUE KEY `date` (`date`)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

    // Default options
	add_option('ecard_label_name_own', 'Your name');
	add_option('ecard_label_email_own', 'Your email address');
	//add_option('ecard_label_name_friend', 'Your friend name');
	add_option('ecard_label_email_friend', 'Your friend email address');
	add_option('ecard_label_message', 'eCard message');
    add_option('ecard_submit', 'Send eCard');

    add_option('ecard_label', 0);
    add_option('ecard_custom_style', 'Vintage');
    add_option('ecard_counter', 0);
    add_option('ecard_behaviour', 1);
    add_option('ecard_link_anchor', 'Click to see your eCard!');

    // email settings
    add_option('ecard_title', 'eCard!');
    add_option('ecard_body_additional', 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.');
    add_option('ecard_body_toggle', 1);

    // members only settings
    add_option('ecard_restrictions', 0);
    add_option('ecard_restrictions_message', 'This section is restricted to members only.');

    // send all eCards to a universal email address
    add_option('ecard_send_behaviour', 1);
    add_option('ecard_hardcoded_email', '');

    //
    add_option('ecard_image_size', 'thumbnail');
    add_option('ecard_shortcode_fix', 'off');

	//
	add_option('ecard_use_akismet', 'false');

	//
	add_role('ecards_sender', __('eCards Sender', 'ecards'), array('read' =>  false, 'edit_posts' => false, 'delete_posts' => false));
}

register_activation_hook(__FILE__, 'eCardsInstall');
//


function display_ecardMe() {
	$ecard_submit = get_option('ecard_submit');

	$ecard_behaviour = get_option('ecard_behaviour');
	$ecard_link_anchor = get_option('ecard_link_anchor');

	// email settings
	$ecard_title = get_option('ecard_title');
	$ecard_body_additional = wpautop(get_option('ecard_body_additional'));
	$ecard_body_toggle = get_option('ecard_body_toggle');

    // send eCard
    // routine
    // since eCards 2.2
	if(isset($_POST['ecard_send'])) {
        $subject = sanitize_text_field($ecard_title);

		if(get_option('ecard_send_behaviour') === '1')
			$ecard_to = sanitize_email($_POST['ecard_to']);
		$ecard_to_name = sanitize_text_field($_POST['ecard_to_name']);
		if(get_option('ecard_send_behaviour') === '0')
			$ecard_to = sanitize_email(get_option('ecard_hardcoded_email'));

		// check if <Mail From> fields are filled in
		$ecard_from = sanitize_text_field($_POST['ecard_from']);
		$ecard_email_from = sanitize_email($_POST['ecard_email_from']);

		$ecard_mail_message = sanitize_text_field($_POST['ecard_message']);

		$ecard_referer = esc_url($_POST['ecard_referer']);

		// gallery (attachments) mode
		if(isset($_POST['ecard_pick_me'])) {
			$ecard_pick_me = sanitize_text_field($_POST['ecard_pick_me']);
			$large = wp_get_attachment_image_src($ecard_pick_me, 'large');
			$ecard_pick_me = '<img src="' . $large[0] . '" alt="">';
		}
		//

		/*
         * MANAGE BEHAVIOURS // OPTIMIZED FROM 2.4.3
        /*
		0. Hide eCard inside email message (show link to source)
		1. Show eCard inside email message (show link to source)
		5. Show eCard inside email message (hide link to source)
		2. Hide both eCard and link to source
		3. Show custom link
		*/

        // begin message
		$ecard_message = '';
		$ecard_body_footer = '';

		//$ecard_message .= '<p><strong>Hej '.$ecard_to_name.'</strong><br>' . $ecard_mail_message . '<br><strong>'.$ecard_from.'</strong></p>';
		$ecard_message .='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
    	<!-- NAME: enkopstorforskel.dk -->
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>enkopstorforskel</title>
        
    <style type="text/css">
	@import url(https://fonts.googleapis.com/css?family=Abel);
	@font-face {
		font-family: dinregular;
		src: url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.eot\');
		src: url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.eot?#iefix\') format(\'embedded-opentype\'), url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.woff2\') format(\'woff2\'), url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.woff\') format(\'woff\'), url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.ttf\') format(\'truetype\'), url(\'http://bordingweb.com/assets/font/font.php?f=din-regular-webfont.svg#dinregular\') format(\'svg\');
		font-weight: normal;
		font-style: normal;
	}
		body,#bodyTable,#bodyCell{
			height:100% !important;
			margin:0;
			padding:0;
			width:100% !important;
		}
		table{
			border-collapse:collapse;
		}
		img,a img{
			border:0;
			outline:none;
			text-decoration:none;
		}
		h1,h2,h3,h4,h5,h6{
			margin:0;
			padding:0;
		}
		p{
			margin:1em 0;
			padding:0;
		}
		a{
			word-wrap:break-word;
		}
		.ReadMsgBody{
			width:100%;
		}
		.ExternalClass{
			width:100%;
		}
		.ExternalClass,.ExternalClass p,.ExternalClass span,.ExternalClass font,.ExternalClass td,.ExternalClass div{
			line-height:100%;
		}
		table,td{
			mso-table-lspace:0pt;
			mso-table-rspace:0pt;
		}
		#outlook a{
			padding:0;
		}
		img{
			-ms-interpolation-mode:bicubic;
		}
		body,table,td,p,a,li,blockquote{
			-ms-text-size-adjust:100%;
			-webkit-text-size-adjust:100%;
		}
		.mcnImage{
			vertical-align:bottom;
		}
		.mcnTextContent img{
			height:auto !important;
		}
		a.mcnButton{
			display:block;
		}
		body,.backgroundColor{
			background-color:#1E1E1E;
			background-position:top left;
			background-repeat:repeat;
		}
	/*
	@tab Page
	@section background style
	@tip Set the background color and top border for your email. You may want to choose colors that match your company\'s branding.
	*/
		#bodyCell{
			border-top:0;
		}
	/*
	@tab Page
	@section email border
	@tip Set the border for your email.
	*/
		#templateContainer{
			border:1px solid #FFFFFF;
		}
	/*
	@tab Page
	@section heading 1
	@tip Set the styling for all first-level headings in your emails. These should be the largest of your headings.
	@style heading 1
	*/
		h1{
			color:#FAFAFA !important;
			display:block;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:26px;
			font-style:normal;
			font-weight:normal;
			line-height:125%;
			letter-spacing:1px;
			margin:0;
			text-align:left;
		}
	/*
	@tab Page
	@section heading 2
	@tip Set the styling for all second-level headings in your emails.
	@style heading 2
	*/
		h2{
			color:#FAFAFA !important;
			display:block;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:22px;
			font-style:normal;
			font-weight:normal;
			line-height:125%;
			letter-spacing:1px;
			margin:0;
			text-align:left;
		}
	/*
	@tab Page
	@section heading 3
	@tip Set the styling for all third-level headings in your emails.
	@style heading 3
	*/
		h3{
			color:#E94683 !important;
			display:block;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:16px;
			font-style:normal;
			font-weight:normal;
			line-height:125%;
			letter-spacing:1px;
			margin:0;
			text-align:left;
		}
	/*
	@tab Page
	@section heading 4
	@tip Set the styling for all fourth-level headings in your emails. These should be the smallest of your headings.
	@style heading 4
	*/
		h4{
			color:#AAAAAA !important;
			display:block;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:16px;
			font-style:normal;
			font-weight:normal;
			line-height:125%;
			letter-spacing:normal;
			margin:0;
			text-align:left;
		}
	/*
	@tab Preheader
	@section preheader style
	@tip Set the background color and borders for your email\'s preheader area.
	*/
		#templatePreheader{
			background-color:#272829;
			border-top:0;
			border-bottom:1px solid #101010;
		}
	/*
	@tab Preheader
	@section preheader text
	@tip Set the styling for your email\'s preheader text. Choose a size and color that is easy to read.
	*/
		.preheaderContainer .mcnTextContent,.preheaderContainer .mcnTextContent p{
			color:#818283;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:10px;
			line-height:125%;
			text-align:left;
		}
	/*
	@tab Preheader
	@section preheader link
	@tip Set the styling for your email\'s header links. Choose a color that helps them stand out from your text.
	*/
		.preheaderContainer .mcnTextContent a{
			color:#818283;
			font-weight:normal;
			text-decoration:underline;
		}
	/*
	@tab Header
	@section header style
	@tip Set the background color and borders for your email\'s header area.
	*/
		#templateHeader{
			background-color:#272829;
			border-top:0;
			border-bottom:0;
		}
	/*
	@tab Header
	@section header text
	@tip Set the styling for your email\'s header text. Choose a size and color that is easy to read.
	*/
		.headerContainer .mcnTextContent,.headerContainer .mcnTextContent p{
			color:#606060;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:16px;
			line-height:150%;
			text-align:left;
		}
	/*
	@tab Header
	@section header link
	@tip Set the styling for your email\'s header links. Choose a color that helps them stand out from your text.
	*/
		.headerContainer .mcnTextContent a{
			color:#3f8893;
			font-weight:normal;
			text-decoration:underline;
		}
	/*
	@tab Body
	@section body style
	@tip Set the background color and borders for your email\'s body area.
	*/
		#templateBody{
			background-color:#272829;
			border-top:0;
			border-bottom:0;
		}
	/*
	@tab Body
	@section body text
	@tip Set the styling for your email\'s body text. Choose a size and color that is easy to read.
	*/
		.bodyContainer .mcnTextContent,.bodyContainer .mcnTextContent p{
			color:#AAAAAA;
			font-family:dinregular;
			font-size:14px;
			line-height:150%;
			text-align:left;
		}
	/*
	@tab Body
	@section body link
	@tip Set the styling for your email\'s body links. Choose a color that helps them stand out from your text.
	*/
		.bodyContainer .mcnTextContent a{
			color:#3F8893;
			font-weight:normal;
			text-decoration:underline;
		}
	/*
	@tab Columns
	@section column style
	@tip Set the background color and borders for your email\'s columns area.
	*/
		#templateColumns{
			background-color:#FFFFFF;
			border-top:0;
		}
	/*
	@tab Columns
	@section left column text
	@tip Set the styling for your email\'s left column text. Choose a size and color that is easy to read.
	*/
		.leftColumnContainer .mcnTextContent,.leftColumnContainer .mcnTextContent p{
			color:#E94683;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:14px;
			line-height:150%;
			text-align:left;
		}
	/*
	@tab Columns
	@section left column link
	@tip Set the styling for your email\'s left column links. Choose a color that helps them stand out from your text.
	*/
		.leftColumnContainer .mcnTextContent a{
			color:#3F8893;
			font-weight:normal;
			text-decoration:underline;
		}
	/*
	@tab Columns
	@section right column text
	@tip Set the styling for your email\'s right column text. Choose a size and color that is easy to read.
	*/
		.rightColumnContainer .mcnTextContent,.rightColumnContainer .mcnTextContent p{
			color:#AAAAAA;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:14px;
			line-height:150%;
			text-align:left;
		}
	/*
	@tab Columns
	@section right column link
	@tip Set the styling for your email\'s right column links. Choose a color that helps them stand out from your text.
	*/
		.rightColumnContainer .mcnTextContent a{
			color:#3F8893;
			font-weight:normal;
			text-decoration:underline;
		}
	/*
	@tab Footer
	@section footer style
	@tip Set the borders for your email\'s footer area.
	*/
		.footerContainer{
			border-top:0;
			border-bottom:0;
		}
	/*
	@tab Footer
	@section footer text
	@tip Set the styling for your email\'s footer text. Choose a size and color that is easy to read.
	*/
		.footerContainer .mcnTextContent,.footerContainer .mcnTextContent p{
			color:#818283;
			font-family: dinregular,\'Abel\', Tahoma;
			font-size:10px;
			line-height:125%;
			text-align:center;
		}
	/*
	@tab Footer
	@section footer link
	@tip Set the styling for your email\'s footer links. Choose a color that helps them stand out from your text.
	*/
		.footerContainer .mcnTextContent a{
			color:#818283;
			font-weight:normal;
			text-decoration:underline;
		}
	@media only screen and (max-width: 480px){
		body,table,td,p,a,li,blockquote{
			-webkit-text-size-adjust:none !important;
		}

}	@media only screen and (max-width: 480px){
		body{
			width:100% !important;
			min-width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnTextContentContainer]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnBoxedTextContentContainer]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcpreview-image-uploader]{
			width:100% !important;
			display:none !important;
		}

}	@media only screen and (max-width: 480px){
		img[class=mcnImage]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnImageGroupContentContainer]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageGroupContent]{
			padding:9px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageGroupBlockInner]{
			padding-bottom:0 !important;
			padding-top:0 !important;
		}

}	@media only screen and (max-width: 480px){
		tbody[class=mcnImageGroupBlockOuter]{
			padding-bottom:9px !important;
			padding-top:9px !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnCaptionTopContent],table[class=mcnCaptionBottomContent]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnCaptionLeftTextContentContainer],table[class=mcnCaptionRightTextContentContainer],table[class=mcnCaptionLeftImageContentContainer],table[class=mcnCaptionRightImageContentContainer],table[class=mcnImageCardLeftTextContentContainer],table[class=mcnImageCardRightTextContentContainer]{
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardLeftImageContent],td[class=mcnImageCardRightImageContent]{
			padding-right:18px !important;
			padding-left:18px !important;
			padding-bottom:0 !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardBottomImageContent]{
			padding-bottom:9px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardTopImageContent]{
			padding-top:18px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardLeftImageContent],td[class=mcnImageCardRightImageContent]{
			padding-right:18px !important;
			padding-left:18px !important;
			padding-bottom:0 !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardBottomImageContent]{
			padding-bottom:9px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnImageCardTopImageContent]{
			padding-top:18px !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=mcnCaptionLeftContentOuter] td[class=mcnTextContent],table[class=mcnCaptionRightContentOuter] td[class=mcnTextContent]{
			padding-top:9px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnCaptionBlockInner] table[class=mcnCaptionTopContent]:last-child td[class=mcnTextContent]{
			padding-top:18px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnBoxedTextContentColumn]{
			padding-left:18px !important;
			padding-right:18px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=mcnTextContent]{
			padding-right:18px !important;
			padding-left:18px !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=columnsContainer]{
			display:block !important;
			max-width:600px !important;
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
		table[class=flexibleContainer]{
			max-width:600px !important;
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section template width
	@tip Make the template fluid for portrait or landscape view adaptability. If a fluid layout doesn\'t work for you, set the width to 300px instead.
	*/
		table[id=templateContainer],table[id=templatePreheader],table[id=templateHeader],table[id=templateBody],table[id=templateColumns],table[id=templateFooter]{
			/*@tab Mobile Styles
@section template width
@tip Make the template fluid for portrait or landscape view adaptability. If a fluid layout doesn\'t work for you, set the width to 300px instead.*/max-width:600px !important;
			width:100% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section heading 1
	@tip Make the first-level headings larger in size for better readability on small screens.
	*/
		h1{
			font-size:24px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section heading 2
	@tip Make the second-level headings larger in size for better readability on small screens.
	*/
		h2{
			font-size:20px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section heading 3
	@tip Make the third-level headings larger in size for better readability on small screens.
	*/
		h3{
			font-size:18px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section heading 4
	@tip Make the fourth-level headings larger in size for better readability on small screens.
	*/
		h4{
			font-size:16px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Boxed Text
	@tip Make the boxed text larger in size for better readability on small screens. We recommend a font size of at least 16px.
	*/
		table[class=mcnBoxedTextContentContainer] td[class=mcnTextContent],td[class=mcnBoxedTextContentContainer] td[class=mcnTextContent] p{
			font-size:18px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Preheader Visibility
	@tip Set the visibility of the email\'s preheader on small screens. You can hide it to save space.
	*/
		table[id=templatePreheader]{
			display:block !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Preheader Text
	@tip Make the preheader text larger in size for better readability on small screens.
	*/
		td[class=preheaderContainer] td[class=mcnTextContent],td[class=preheaderContainer] td[class=mcnTextContent] p{
			font-size:14px !important;
			line-height:115% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Header Text
	@tip Make the header text larger in size for better readability on small screens.
	*/
		td[class=headerContainer] td[class=mcnTextContent],td[class=headerContainer] td[class=mcnTextContent] p{
			font-size:18px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Body Text
	@tip Make the body text larger in size for better readability on small screens. We recommend a font size of at least 16px.
	*/
		td[class=bodyContainer] td[class=mcnTextContent],td[class=bodyContainer] td[class=mcnTextContent] p{
			font-size:18px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Left Column Text
	@tip Make the left column text larger in size for better readability on small screens. We recommend a font size of at least 16px.
	*/
		td[class=leftColumnContainer] td[class=mcnTextContent],td[class=leftColumnContainer] td[class=mcnTextContent] p{
			font-size:15px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section Right Column Text
	@tip Make the right column text larger in size for better readability on small screens. We recommend a font size of at least 16px.
	*/
		td[class=rightColumnContainer] td[class=mcnTextContent],td[class=rightColumnContainer] td[class=mcnTextContent] p{
			font-size:18px !important;
			line-height:125% !important;
		}

}	@media only screen and (max-width: 480px){
	/*
	@tab Mobile Styles
	@section footer text
	@tip Make the body content text larger in size for better readability on small screens.
	*/
		td[class=footerContainer] td[class=mcnTextContent],td[class=footerContainer] td[class=mcnTextContent] p{
			font-size:14px !important;
			line-height:115% !important;
		}

}	@media only screen and (max-width: 480px){
		td[class=footerContainer] a[class=utilityLink]{
			display:block !important;
		}

}</style></head>
    <body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
        <div class="backgroundColor">
            <!--[if gte mso 9]>
                <v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
            	</v:background>
            <![endif]-->
            <center>
                <table align="center" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">
                    <tr>
                        <td align="center" valign="top" id="bodyCell">
                            <!-- BEGIN TEMPLATE // -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" valign="top" style="padding-top:1px; padding-right:6px; padding-bottom:9px; padding-left:6px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="600" id="templateContainer">
                                            <tr>
                                                <td align="center" valign="top">
                                                    <!-- BEGIN HEADER // -->
                                                    <table border="0" cellpadding="0" cellspacing="0" width="600" id="templateHeader">
                                                        <tr>
                                                            <td valign="top" class="headerContainer"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnImageBlock">
    <tbody class="mcnImageBlockOuter">
            <tr>
                <td valign="top" style="padding:0px" class="mcnImageBlockInner">
                    <table align="left" width="100%" border="0" cellpadding="0" cellspacing="0" class="mcnImageContentContainer">
                        <tbody><tr>
                            <td class="mcnImageContent" valign="top" style="padding-right: 0px; padding-left: 0px; padding-top: 0; padding-bottom: 0; text-align:center;">
                                
                                    
                                        <img align="center" alt="" src="http://bordingweb.com/clients/lillekopstorforskel.dk/wp-content/uploads/2015/09/email-banner.jpg" width="600" style="max-width:600px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage">
                                    
                                
                            </td>
                        </tr>
                    </tbody></table>
                </td>
            </tr>
    </tbody>
</table></td>
                                                        </tr>
                                                    </table>
                                                    <!-- // END HEADER -->
                                                </td>
                                            </tr>
                                             <tr>
                                                <td align="center" valign="top">
                                                    <!-- BEGIN COLUMNS // -->
                                                    <table bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0" width="600" id="templateColumns">
                                                        <tr>
                                                            <td align="left" valign="top" width="50%" class="columnsContainer">
                                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="templateColumn">
                                                                    <tr>
                                                                        <td valign="top" class="leftColumnContainer"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="top" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="300" class="mcnTextContentContainer">
                    <tbody>
                    	<tr><td valign="top" height="20"></td></tr>
                    <tr>
                        
                        <td valign="top" class="mcnTextContent" style="padding-top:9px; padding-right: 18px; padding-bottom: 9px; padding-left: 18px; color: #E94683;">
                        
                            <h3 style="font-family:dinregular; font-weight: bold;font-size: 1.4rem;">Lille kop stor forskel</h3>
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
        </tr>
    </tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="top" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="300" class="mcnTextContentContainer">
                    <tbody><tr>
                        
                        <td valign="top" class="mcnTextContent" style="font-family: dinregular,\'Abel\', Tahoma; padding-top:9px; padding-right: 18px; padding-bottom: 9px; padding-left: 18px;color: #E94683;font-size: 12px;line-height: 1.3;">
                        Lipton og BKI har indgået et samarbejde med Kærftens Bekæmpelse og Støt Brysterne. Et samarbejde der, ved noget så simpelt som en skænket kop te, vil gøre en forskel i kampen mod brystkræft. For hver skænket kop Lipton te doneres nemlig 5 øre til Kræftens Bekæmpelses kamp mod brystkræft.
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
        </tr>
    </tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnButtonBlock">
    <tbody class="mcnButtonBlockOuter">
        <tr>
            <td style="padding-top:0; padding-right:18px; padding-bottom:18px; padding-left:18px;" valign="top" align="left" class="mcnButtonBlockInner">
                <table border="0" cellpadding="0" cellspacing="0" class="mcnButtonContentContainer" style="border: 0;border-radius: 0;background-color: #FFFFFF;">
                    <tbody>
                        <tr>
                            <td align="center" valign="middle" class="mcnButtonContent" style="font-family: dinregular,\'Abel\', Tahoma; font-size: 12px; padding: 0;">
                                <img align="center" alt="" src="http://bordingweb.com/clients/lillekopstorforskel.dk/wp-content/uploads/2015/09/cup-of-tea.jpg" width="260" style="max-width:260px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td align="left" valign="top" width="50%" class="columnsContainer">
                                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="templateColumn">
                                                                    <tr>
                                                                        <td valign="top" class="rightColumnContainer"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="top" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="300" class="mcnTextContentContainer">
                    <tbody>
                    	<tr><td valign="top" height="20"></td></tr>
                    	<tr>
                            <td valign="top" class="mcnTextContent" style="padding-top:9px; padding-right: 18px; padding-bottom: 9px; padding-left: 18px;">                 
                            <h3 style="font-family: dinregular,\'Abel\', Tahoma; color: #000000!important;">Hej&nbsp;'.$ecard_to_name .'</h3>
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
        </tr>
    </tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnFollowBlock">
    <tbody class="mcnFollowBlockOuter">
        <tr>
            <td align="center" valign="top" style="padding:9px" class="mcnFollowBlockInner">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tbody><tr>
        <td align="left" style="padding-left:9px; padding-right:9px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnFollowContentContainer">
                <tbody><tr>
                    <td align="left" valign="top" style="padding:0;" class="mcnFollowContent">
						<table border="0" cellpadding="0" cellspacing="0">
							<tbody><tr>
								<td align="left" valign="top" style="font-size:13px;font-family: dinregular,\'Abel\', Tahoma; color: #000000!important;">
			                    ' .$ecard_mail_message.'
								</td>
							</tr>
						</tbody></table>
                    </td>
                </tr>
            </tbody></table>
        </td>
    </tr>
</tbody></table>

            </td>
        </tr>
    </tbody>
</table><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock">
    <tbody class="mcnDividerBlockOuter">
        <tr>
            <td class="mcnDividerBlockInner" style="padding: 18px;">
                <table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tbody><tr>
                        <td>
                            <span style="font-family: dinregular,\'Abel\', Tahoma; color: #000000!important;">' .$ecard_from. '</span>
                        </td>
                    </tr>
                </tbody></table>
            </td>
        </tr>
    </tbody>
</table></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <!-- // END COLUMNS -->
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" valign="top">
                                                    <!-- BEGIN COLUMNS // -->
                                                    <table bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0" width="600" id="templateColumns">
                                                        <tr>
                                                            <td align="left" valign="bottom" width="50%" class="columnsContainer">
                                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="templateColumn">
                                                                    <tr>
                                                                        <td valign="top" class="leftColumnContainer"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="bottom" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="300" class="mcnTextContentContainer">
                    <tbody><tr>
                        
                        <td valign="top" class="mcnTextContent" style="padding-top:9px; padding-right: 0; padding-bottom: 9px; padding-left: 18px; line-height:1.1;">
                        
                            <p style="font-family: dinregular,\'Abel\', Tahoma; font-size: 0.6rem;">Læs mere om indsamlingen på: <a style="font-family:dinregular; font-size: 0.6rem; color: #E94683;" href="http://www.lillekopstorforskel.dk">www.lillekopstorforskel.dk</a></p>
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
        </tr>
    </tbody>
</table> 
</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td align="left" valign="top" width="50%" class="columnsContainer">
                                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="templateColumn">
                                                                    <tr>
                                                                        <td valign="top" class="rightColumnContainer"><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="bottom" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="160" class="mcnTextContentContainer">
                    <tbody><tr>
                        
                        <td valign="top" class="mcnTextContent" style="padding-top:9px; padding-right: 10px; padding-bottom: 9px; padding-left: 0;">
                        
                            <img align="center" alt="" src="http://bordingweb.com/clients/lillekopstorforskel.dk/wp-content/uploads/2015/09/flogo-2.jpg" width="150" style="max-width:150px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage">
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
                        <td valign="top" class="mcnTextBlockInner">
                
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="140" class="mcnTextContentContainer">
                    <tbody><tr>
                        
                        <td valign="top" class="mcnTextContent" style="padding-top:9px; padding-right: 10px; padding-bottom: 9px; padding-left: 0;">
                        
                            <img align="center" alt="" src="http://bordingweb.com/clients/lillekopstorforskel.dk/wp-content/uploads/2015/09/flogo-1.jpg" width="100" style="max-width:100px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage">
                        </td>
                    </tr>
                </tbody></table>
                
            </td>
        </tr>
    </tbody>
</table>  </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <!-- // END COLUMNS -->
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                            </table>
                            <!-- // END TEMPLATE -->
                        </td>
                    </tr>
                </table>
            </center>
        </div>
    </body>
</html>';
        if(get_option('ecard_behaviour') === '0') {
			$ecard_message 	.= '<p>' . wp_get_attachment_link(sanitize_text_field($_POST['ecard_pick_me']), 'thumbnail', true, false, $ecard_link_anchor) . '</p>';
			$ecard_message 	.= '<p><small><a href="' . $ecard_referer . '">' . $ecard_referer . '</a></small></p>';
		}
		if(get_option('ecard_behaviour') === '1') {
			if(!empty($_POST['selected-file'])) {
				$ecard_message 	.= '<p><b><a href="' . esc_url($_POST['selected-file']) . '">' . esc_url($_POST['selected-file']) . '</a></b></p>';
				$ecard_body_footer .= '<p><small><a href="' . $ecard_referer . '">' . $ecard_referer . '</a></small></p>';
			}
			else {
                if(!empty($ecard_pick_me)) { // if there's no selected eCard (only user uploaded one)
				    $ecard_message 	.= '<p>' . $ecard_pick_me . '</p>';
                    $ecard_body_footer .= '<p>' . wp_get_attachment_link(sanitize_text_field($_POST['ecard_pick_me']), 'thumbnail', true, false, $ecard_link_anchor) . '</p>';
                }
			}
		}
		if(get_option('ecard_behaviour') === '2') {
			$ecard_message 	.= '';
		}
		if(get_option('ecard_behaviour') === '3') {
			$ecard_message 	.= '';
		}
		if(get_option('ecard_behaviour') === '5') {
			if(!empty($_POST['selected-file']))
				$ecard_message 	.= '<p><b><a href="' . esc_url($_POST['selected-file']) . '">' . esc_url($_POST['selected-file']) . '</a></b></p>';
			else
				$ecard_message 	.= '<p>' . $ecard_pick_me . '</p>';
		}

		$ecard_message .= '<p>' . $ecard_body_additional . '</p>';
		$ecard_message .= '<p>' . $ecard_body_footer . '</p>';
		$ecard_message .= '<p>&nbsp;</p>';

		$attachments = '';

        $headers = '';
        $headers[] .= "Content-type: text/html" ;
        $headers[] .= "To: $ecard_to <$ecard_email_to>;";

		// Akismet
		$content['comment_author'] = $ecard_from;
		$content['comment_author_email'] = $ecard_email_from;
		$content['comment_author_url'] = home_url();
		$content['comment_content'] = $ecard_message;

		if(ecard_checkSpam($content)) {
			echo '<p><strong>' . __('Akismet prevented sending of this eCard and marked it as spam!', 'ecards') . '</strong></p>';
		}
		else {
			wp_mail($ecard_to, $subject, $ecard_message, $headers, $attachments);

			echo '<p class="ecard-confirmation"><strong>' . __('eCard sent successfully!', 'ecards') . '</strong></p>';
			ecards_save();
		}
	}

	$output = '';

	$output .= '<div class="ecard-container">';
		$output .= '<form action="#" method="post" enctype="multipart/form-data"><div class="row"><div class="small-12 medium-6 large-6 columns">';

        // get all post attachments
        $args = array(
            'post_type'         => 'attachment',
            'numberposts'       => -1,
            'post_status'       => null,
            'post_parent'       => get_the_ID(),
            'post_mime_type'    => 'image',
            'orderby'           => 'menu_order',
            'order'             => 'ASC',
            'exclude'           => get_post_thumbnail_id(get_the_ID()),
        );
        $attachments = get_posts($args);

        $ecard_image_size = get_option('ecard_image_size');

        if($attachments) {
            if(count($attachments) == 1)
                $hide_radio = 'style="display: none;"';
            else
                $hide_radio = '';

            $output .= '<div role="radiogroup">';
                foreach($attachments as $a) {
                    $alt = get_post_meta($a->ID, '_wp_attachment_image_alt', true);
                    if($alt != 'noselect') {
                        $output .= '<div class="ecard">';
                            $large = wp_get_attachment_image_src($a->ID, 'large');
                            $thumb = wp_get_attachment_image($a->ID, $ecard_image_size);
							if(get_option('ecard_label') == 0) {
								$output .= '<a href="' . $large[0] . '" class="ecards">' . $thumb . '</a><br><input type="radio" name="ecard_pick_me" id="ecard' . $a->ID . '" value="' . $a->ID . '" ' . $hide_radio . ' checked><label for="ecard' . $a->ID . '"></label>';
							}
							if(get_option('ecard_label') == 1) {
								$output .= '<label for="ecard' . $a->ID . '">' . $thumb . '<br><input type="radio" name="ecard_pick_me" id="ecard' . $a->ID . '" value="' . $a->ID . '" ' . $hide_radio . ' checked></label>';
							}
                        $output .= '</div>';
                    }
                }
                $output .= '<div style="clear:both;"></div>';
            $output .= '</div>';
        }
        // end
	    if($ecard_body_toggle === '1')
        $output .= '<p>' . get_option('ecard_label_message') . '</p><span><textarea name="ecard_message" rows="13" cols="60"></textarea></span>';
		if($ecard_body_toggle === '0')
        $output .= '<input type="hidden" name="ecard_message">';
	
	$output .= '</div><div class="small-12 medium-6 large-6 columns"><p>Modtager</p><span><input type="text" name="ecard_from" size="30" placeholder="Your name" required> ' . get_option('ecard_label_name_own') .'</span>';
	$output .= '<span><input type="email" name="ecard_email_from" size="30" placeholder="Your email address" required> ' . get_option('ecard_label_email_own') . '</span>';

	if(get_option('ecard_send_behaviour') === '1')
		$output .= '<span><input type="text" name="ecard_to_name" size="30" placeholder="Your friend name" required> ' . get_option('') . '</span>';
        $output .= '<span><input type="email" name="ecard_to" size="30" placeholder="Your friend email address" required> ' . get_option('ecard_label_email_friend') . '</span>';



			$output .= '<span>
				<input type="hidden" name="ecard_referer" value="' . get_permalink() . '">
				<input type="submit" name="ecard_send" value="' . $ecard_submit . '" class="button blue">
			</span>';
			
			
			
			
		$output .= '</div></div></form>';
	$output .= '</div>';

	if(get_option('ecard_restrictions') === '0')
		return $output;
	if(get_option('ecard_restrictions') === '1' && is_user_logged_in())
		return $output;
	if(get_option('ecard_restrictions') === '1' && !is_user_logged_in())
		$output = get_option('ecard_restrictions_message');

	return $output;
}

function display_ecardCounter() {
	$ecard_counter = get_option('ecard_counter');

	return $ecard_counter;
}

add_shortcode('ecard', 'display_ecardMe');
add_shortcode('ecard_counter', 'display_ecardCounter');

add_action('wp_enqueue_scripts', 'ecard_enqueue_scripts');
function ecard_enqueue_scripts() {
	if(get_option('ecard_custom_style') === 'Vintage')
		wp_enqueue_style('ecards', plugins_url('css/vintage.css', __FILE__));
	if(get_option('ecard_custom_style') === 'MetroL')
		wp_enqueue_style('ecards', plugins_url('css/metro-light.css', __FILE__));
	if(get_option('ecard_custom_style') === 'MetroD')
		wp_enqueue_style('ecards', plugins_url('css/metro-dark.css', __FILE__));

	if(get_option('ecard_custom_style') === 'Theme')
		wp_enqueue_style('ecards', plugins_url('css/extended.css', __FILE__));
}

// Displays options menu
function ecard_add_option_page() {
	add_options_page('eCards', 'eCards', 'manage_options', 'ecards', 'ecard_options_page');
}

add_action('admin_menu', 'ecard_add_option_page');

// custom settings link inside Plugins section
function ecards_settings_link($links) { 
	$settings_link = '<a href="options-general.php?page=ecards">Settings</a>'; 
	array_unshift($links, $settings_link); 
	return $links; 
}
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'ecards_settings_link');
?>
