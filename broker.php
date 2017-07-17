<?php
/*
Copyright 2012 Blindside Networks
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
Versions:
   1.0  --  Updated by Jesus Federico
                    (email : federico DOT jesus [a t ] g m ail DOT com)
*/
///================================================================================
//------------------Required Libraries and Global Variables-----------------------
//================================================================================
require 'includes/bbb_api.php';
require($_SERVER['DOCUMENT_ROOT'].'/wordpress-test/wp-load.php');//MAKE SUREE TO CHNAGE THE PATH
session_start();
$bbb_endpoint_name = 'mt_bbb_endpoint';
$bbb_secret_name = 'mt_bbb_secret';
$action_name = 'action';
$recordingID_name = 'recordingID';
$meetingID_name = 'meetingID';
$slug_name = 'slug';
$join = 'join';
$password = 'password';
$joinurl='';
//================================================================================
//------------------------------------Main----------------------------------------
//================================================================================
//Retrieves the bigbluebutton url, and salt from the seesion
if (!isset($_SESSION[$bbb_secret_name]) || !isset($_SESSION[$bbb_endpoint_name])) {
    header('HTTP/1.0 400 Bad Request. BigBlueButton_CPT Url or Salt are not accessible.');
} elseif (!isset($_GET[$action_name])) {
    header('HTTP/1.0 400 Bad Request. [action] parameter was not included in this query.');
} else {
    $salt_val = $_SESSION[$bbb_secret_name];
    $url_val = $_SESSION[$bbb_endpoint_name];
    $action = $_GET[$action_name];
    switch ($action) {
        case 'publish':
            header('Content-Type: text/plain; charset=utf-8');
            if (!isset($_GET[$recordingID_name])) {
                header('HTTP/1.0 400 Bad Request. [recordingID] parameter was not included in this query.');
            } else {
                $recordingID = $_GET[$recordingID_name];
                echo BigBlueButton::doPublishRecordings($recordingID, 'true', $url_val, $salt_val);
            }
            break;
        case 'unpublish':
            header('Content-Type: text/plain; charset=utf-8');
            if (!isset($_GET[$recordingID_name])) {
                header('HTTP/1.0 400 Bad Request. [recordingID] parameter was not included in this query.');
            } else {
                $recordingID = $_GET[$recordingID_name];
                echo BigBlueButton::doPublishRecordings($recordingID, 'false', $url_val, $salt_val);
            }
            break;
        case 'delete':
            header('Content-Type: text/plain; charset=utf-8');
            if (!isset($_GET[$recordingID_name])) {
                header('HTTP/1.0 400 Bad Request. [recordingID] parameter was not included in this query.');
            } else {
                $recordingID = $_GET[$recordingID_name];
                echo BigBlueButton::doDeleteRecordings($recordingID, $bbb_endpoint_name, $salt_val);
            }
            break;
        case 'ping':
           $post = get_page_by_path($_POST[$slug_name], OBJECT, 'bbb-room');
                $meetingID = $_GET[$meetingID_name];
                  $password = setPassword($post);
                $response = BigBlueButton::getMeetingXML($meetingID, $url_val, $_SESSION[$bbb_secret_name]);
                if((strpos($response,"true") !== false)){
                  $bigbluebuttonJoinURL = BigBlueButton::getJoinURL($meetingID, $_POST['name'], $password , $_SESSION[$bbb_secret_name], $url_val);
                  echo $bigbluebuttonJoinURL.'';
                }else{
                  echo 'false';
                }
            break;
        case 'join'://cant join when editor (in old plugin, it direcclty says ""sory you are nto allowe ti oin this page)
            //post is not recognizing '+' and '&'
            if((!isset($_POST[$slug_name]))){
                header('HTTP/1.0 400 Bad Request. [slug] parameter was not included in this query.');
            }else if((!isset($_POST[$join]))){
                header('HTTP/1.0 400 Bad Request. [join] parameter was not included in this query.');
            }else{
              $post = get_page_by_path($_POST[$slug_name], OBJECT, 'bbb-room');

              if($_POST[$join] === "true"){
                $username = $current_user->display_name;
                if($username == '' || $username == null){
                  $username = $_POST['name'];
                }
                $bbbRoomToken = get_post_meta($post->ID, '_bbb_room_token', true);
                $meetingID = $bbbRoomToken;
                if(strlen($meetingID) == 12){
                  $meetingID = sha1(home_url().$meetingID);
                }
                $meetingName = get_the_title($post->ID);
                $welcomeString = get_post_meta($post->ID, '_bbb_room_welcome_msg', true);
                $moderatorPassword = get_post_meta($post->ID, '_bbb_moderator_password', true);
                $attendeePassword = get_post_meta($post->ID, '_bbb_attendee_password', true);
                $bbbIsRecorded = get_post_meta($post->ID, '_bbb_is_recorded', true);
                $logoutURL = (is_ssl() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?logout=true';
                $bigbluebuttonSettings = get_option('bigbluebutton_custom_post_type_settings');
                $endpointVal = $bigbluebuttonSettings['endpoint'];//getting these double cause setting $seession. clean it up
                $secretVal = $bigbluebuttonSettings['secret'];
                $bbbWaitForAdminStart = get_post_meta($post->ID, '_bbb_must_wait_for_admin_start', true);
                $password = setPassword($post);

                $response = BigBlueButton::createMeetingArray($username, $meetingID, $meetingName, $welcomeString, $moderatorPassword, $attendeePassword,
                $secretVal, $endpointVal, $logoutURL, $bbbIsRecorded ? 'true' : 'false', $duration = 0,$voiceBridge = 0, $metadata = array());

                if (!$response || $response['returncode'] == 'FAILED') {
                    echo "Sorry an error occured while creating the meeting room.";
                }else {
                    $bigbluebuttonJoinURL = BigBlueButton::getJoinURL($meetingID, $username, $password, $secretVal, $endpointVal);
                    $isMeetingRunning = BigBlueButton::isMeetingRunning($meetingID, $endpointVal, $secretVal);

                    if (($isMeetingRunning && ($moderatorPassword == $password || $attendeePassword == $password))
                         || $response['moderatorPW'] == $password
                         || ($response['attendeePW'] == $password && !$bbbWaitForAdminStart)) {
                          echo $bigbluebuttonJoinURL;
                    }
                    elseif ($attendeePassword == $password) {

                        echo $meetingID;
                    }
                }
              }else {
                if($post !== null){
                  echo get_permalink();
                }else {
                  echo "Sorry the page could not be viewed";
                }
              }
            }
            break;
        default:
            header('Content-Type: text/plain; charset=utf-8');
            echo BigBlueButton::getServerVersion($url_val);
    }
}

function setPassword($post){
  $current_user = wp_get_current_user();
  $password = '';
  $moderatorPassword = get_post_meta($post->ID, '_bbb_moderator_password', true);
  $attendeePassword = get_post_meta($post->ID, '_bbb_attendee_password', true);

  if(is_user_logged_in() == true) {
    $userCapArray = $current_user->allcaps;

  }else {
    $anonymousRole = get_role('anonymous');
    $userCapArray = $anonymousRole->capabilities;
  }

  if($userCapArray["join_with_password_bbb-room"] == true ) {
      if($userCapArray["join_as_moderator_bbb-room"] == true) {
        if(strcmp($moderatorPassword,$_POST['password']) === 0) {
            $password = $moderatorPassword;
        }
      }else {
        if(strcmp($attendeePassword,$_POST['password']) === 0) {
            $password = $attendeePassword;
        }
      }
  }else {
      if($userCapArray["join_as_moderator_bbb-room"] === true) {
        $password = $moderatorPassword;
      }else {
        $password = $attendeePassword;
      }
  }
  return $password;
}
