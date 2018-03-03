<?php

/*
Plugin Name: Coinhive Captcha for WordPress Comments
Plugin URI: https://blanboom.org/2018/coinhive-captcha
Description: 发表评论时，使用 Coinhive 验证码进行验证
Version: 1.2
Author: Blanboom
Author URI: http://blanboom.org
License: GPL2
*/

/* 原插件信息：
 * Plugin Name: Add reCAPTCHA to comment form
 * Plugin URI: http://sitepoint.com
 * Description: Add Google's reCAPTCHA to WordPress comment form
 * Version: 1.0
 * Author: Agbonghama Collins
 * Author URI: http://w3guy.com
 * License: GPL2
 */

class Captcha_Comment_Form {

	/** @type string private key|public key */
	private $public_key, $private_key;

	/** @type  string captcha errors */
	private static $captcha_error;


	/** class constructor */
	public function __construct() {

		$this->public_key  = '1Egl6gZ5ifNkxgpHDFuwtT5eifXFoPYg';
		$this->private_key = '!!!!!!!!!!!!!!!!!!!!!!YOUR_PRIVATE_KEY!!!!!!!!!!!!!!!!!!!!!!';

		// adds the captcha to the comment form
		add_action( 'comment_form', array( $this, 'captcha_display' ) );

		// delete comment that fail the captcha challenge
		add_action( 'wp_head', array( $this, 'delete_failed_captcha_comment' ) );

		// authenticate the captcha answer
		add_filter( 'preprocess_comment', array( $this, 'validate_captcha_field' ) );

		// redirect location for comment
		add_filter( 'comment_post_redirect', array( $this, 'redirect_fail_captcha_comment' ), 10, 2 );
	}


	/** Output the reCAPTCHA form field. */
	public function captcha_display() {
		if ( isset( $_GET['captcha'] ) && $_GET['captcha'] == 'failed' ) {
			echo '<strong>错误</strong>: 验证失败，请单击“Verify me”，并等待进度条走完后再提交评论<br>';
		}
		
		 echo <<<CAPTCHA_FORM
		 <style type='text/css'>#submit {
			display: none;
		}</style>
		 单击“Verify me”，验证后即可评论（<u><a href="https://blanboom.org/2018/coinhive-captcha" target="_blank">了解更多</a></u>）
		<script src="https://authedmine.com/lib/captcha.min.js" async></script>
		<script>
			function captchaCallback(token) {
				document.getElementById('submit').style.display='inline'
		}
		</script>
		<div class="coinhive-captcha" data-key="$this->public_key" data-hashes="1024" data-callback="captchaCallback">
			<em>验证码加载中......<br>
			如果验证码无法加载，请关闭广告过滤软件，或打开浏览器中的 JavaScript</em>
		</div>
CAPTCHA_FORM;
	}


	/**
	 * Add query string to the comment redirect location
	 *
	 * @param $location string location to redirect to after comment
	 * @param $comment object comment object
	 *
	 * @return string
	 */
	function redirect_fail_captcha_comment( $location, $comment ) {

		if ( ! empty( self::$captcha_error ) ) {
			// replace #comment- at the end of $location with #commentform

			$args = array( 'comment-id' => $comment->comment_ID );

			if ( self::$captcha_error == 'captcha_empty' ) {
				$args['captcha'] = 'empty';
			} elseif ( self::$captcha_error == 'challenge_failed' ) {
				$args['captcha'] = 'failed';
			}

			$location = add_query_arg( $args, $location );
		}

		return $location;
	}


	/** Delete comment that fail the captcha test. */
	function delete_failed_captcha_comment() {
		if ( isset( $_GET['comment-id'] ) && ! empty( $_GET['comment-id'] ) ) {

			wp_delete_comment( absint( $_GET['comment-id'] ) );
		}
	}


	/**
	 * Verify the captcha answer
	 *
	 * @param $commentdata object comment object
	 *
	 * @return object
	 */
	public function validate_captcha_field( $commentdata ) {

		if ( $this->recaptcha_response() == 'false' ) {
			self::$captcha_error = 'challenge_failed';
		}

		return $commentdata;
	}


	/**
	 * Get the reCAPTCHA API response.
	 *
	 * @return string
	 */
	public function recaptcha_response() {
		
		$post_data = [
			'secret' => "$this->private_key", // <- Your secret key
			'token' => $_POST['coinhive-captcha-token'],
			'hashes' => 1024
		];

		$post_context = stream_context_create([
			'http' => [
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($post_data)
			]
		]);

		$url = 'https://api.coinhive.com/token/verify';
		$response = json_decode(file_get_contents($url, false, $post_context));

		if ($response && $response->success) {
			return 'true';
		} else {
			return 'false';
		}
	}

}

new Captcha_Comment_Form();
