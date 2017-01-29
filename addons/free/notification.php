<?php
/**
 * An AnsPress user notification addons.
 *
 * @author    Rahul Aryan <support@anspress.io>
 * @copyright 2017 AnsPress.io & Rahul Aryan
 * @license   GPL-3.0+ https://www.gnu.org/licenses/gpl-3.0.txt
 * @link      https://anspress.io
 * @package   WordPress/AnsPress/Notification
 *
 * Addon Name:    Notification
 * Addon URI:     https://anspress.io
 * Description:   User notifications.
 * Author:        Rahul Aryan
 * Author URI:    https://anspress.io
 * Pro:    				Yes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Require functions.
require_once( ANSPRESS_ADDONS_DIR . '/free/notification/functions.php' );
require_once( ANSPRESS_ADDONS_DIR . '/free/notification/query.php' );

/**
 * AnsPress notification hooks.
 *
 * @package AnsPress
 * @author  Rahul Aryan <support@anspress.io>
 * @since   4.0.0
 */
class AnsPress_Notification_Hook {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		ap_add_default_options([
			'noti_delete_interval' => 3600,
			'noti_delete_read'     => true,
		]);

		anspress()->add_action( 'ap_option_groups', __CLASS__, 'load_options' );
		anspress()->add_filter( 'ap_user_tab', __CLASS__, 'ap_author_tab' );
		anspress()->add_filter( 'ap_user_content', __CLASS__, 'ap_author_content' );
		anspress()->add_action( 'ap_after_new_answer', __CLASS__, 'new_answer', 10, 2 );
		anspress()->add_action( 'ap_trash_question', __CLASS__, 'trash_question', 10, 2 );
		anspress()->add_action( 'ap_before_delete_question', __CLASS__, 'trash_question', 10, 2 );
		anspress()->add_action( 'ap_trash_answer', __CLASS__, 'trash_answer', 10, 2 );
		anspress()->add_action( 'ap_before_delete_answer', __CLASS__, 'trash_answer', 10, 2 );
		anspress()->add_action( 'ap_untrash_answer', __CLASS__, 'new_answer', 10, 2 );
		anspress()->add_action( 'ap_select_answer', __CLASS__, 'select_answer' );
		anspress()->add_action( 'ap_unselect_answer', __CLASS__, 'unselect_answer' );
		anspress()->add_action( 'ap_publish_comment', __CLASS__, 'new_comment' );
		anspress()->add_action( 'ap_unpublish_comment', __CLASS__, 'delete_comment' );
		anspress()->add_action( 'ap_vote_up', __CLASS__, 'vote_up' );
		anspress()->add_action( 'ap_vote_down', __CLASS__, 'vote_down' );
		anspress()->add_action( 'ap_undo_vote_up', __CLASS__, 'undo_vote_up' );
		anspress()->add_action( 'ap_undo_vote_down', __CLASS__, 'undo_vote_down' );
	}

	/**
	 * Register Avatar options
	 */
	public static function load_options() {

		ap_register_option_section( 'addons', basename( __FILE__ ), __( 'Notification', 'anspress-question-answer' ), array(
			array(
				'name'              => 'clear_avatar_cache',
				'type'              => 'custom',
				'html' => '<label class="ap-form-label" for="avatar_font">' . __( 'Clear Cache', 'anspress-question-answer' ) . '</label><div class="ap-form-fields-in"><a id="ap-clear-avatar" href="#" class="button">' . __( 'Clear avatar cache', 'anspress-question-answer' ) . '</a></div>',
			),

		));
	}

	/**
	 * Adds reputations tab in AnsPress authors page.
	 */
	public static function ap_author_tab() {
		$user_id = get_query_var( 'ap_user_id' );
	 	$current_tab = ap_sanitize_unslash( 'tab', 'r', 'questions' );

		?>
			<li<?php echo 'notifications' === $current_tab ? ' class="active"' : ''; ?>>
				<a href="<?php echo ap_user_link( $user_id ) . '?tab=notifications'; ?>"><?php esc_attr_e( 'Notifications', 'anspress-question-answer' ); ?></a>
			</li>
		<?php
	}

	/**
	 * Display reputation tab content in AnsPress author page.
	 */
	public static function ap_author_content() {
		$user_id = get_query_var( 'ap_user_id' );
	 	$current_tab = ap_sanitize_unslash( 'tab', 'r', 'notifications' );

		if ( 'notifications' === $current_tab ) {
			$notifications = new AnsPress_Notification_Query( [ 'user_id' => $user_id ] );
			include ap_get_theme_location( 'notifications/index.php' );
		}
	}

	/**
	 * Remove all notifications related to question when its get deleted.
	 *
	 * @param integer $post_id Post ID.
	 * @param object  $_post Post object.
	 */
	public static function trash_question( $post_id, $_post ) {
		ap_delete_notifications( array(
			'parent'   => $post_id,
			'ref_type' => [ 'answer', 'vote_up', 'vote_down', 'post' ],
		) );
	}

	/**
	 * Add notification for new answer.
	 *
	 * @param integer $post_id Post ID.
	 * @param object  $_post Post object.
	 */
	public static function new_answer( $post_id, $_post ) {
		$_question = get_post( $_post->post_parent );
		ap_insert_notification( array(
			'user_id'  => $_post->post_author,
			'actor'    => $_question->post_author,
			'parent'   => $_post->post_parent,
			'ref_id'   => $_post->ID,
			'ref_type' => 'answer',
			'verb'     => 'new_answer',
		) );
	}

	/**
	 * Remove all notifications related to answer when its get deleted.
	 *
	 * @param integer $post_id Post ID.
	 * @param object  $_post Post object.
	 */
	public static function trash_answer( $post_id, $_post ) {
		ap_delete_notifications( array(
			'ref_id'   => $post_id,
			'ref_type' => [ 'answer', 'vote_up', 'vote_down', 'post' ],
		) );
	}

	/**
	 * Notify user when their answer is selected.
	 *
	 * @param object $_post Post object.
	 */
	public static function select_answer( $_post ) {
		$question = get_post( $_post->post_parent );

		// Award select answer points to question author only.
		if ( get_current_user_id() !== $_post->post_author ) {
			ap_insert_notification( array(
				'user_id'  => $question->post_author,
				'actor'    => get_current_user_id(),
				'parent'   => $_post->post_parent,
				'ref_id'   => $_post->ID,
				'ref_type' => 'answer',
				'verb'     => 'best_answer',
			) );
		}
	}

	/**
	 * Remove notification when users answer get unselected.
	 *
	 * @param object $_post Post object.
	 */
	public static function unselect_answer( $_post ) {
		ap_delete_notifications( array(
			'parent'   => $_post->post_parent,
			'ref_type' => 'answer',
			'verb'     => 'best_answer',
		) );
	}

	/**
	 * Notify user on new comment.
	 *
	 * @param  object $comment WordPress comment object.
	 */
	public static function new_comment( $comment ) {
		$_post = get_post( $comment->comment_post_ID );

		if ( get_current_user_id() !== $_post->post_author ) {
			ap_insert_notification( array(
				'user_id'  => $_post->post_author,
				'actor'    => $comment->user_id,
				'ref_id'   => $comment->comment_ID,
				'ref_type' => 'comment',
				'verb'     => 'new_comment',
			) );
		}
	}

	/**
	 * Remove notification on deleting comment.
	 *
	 * @param  object $comment Comment object.
	 */
	public static function delete_comment( $comment ) {
		ap_delete_notifications( array(
			'actor'    => $comment->user_id,
			'ref_id'   => $comment->comment_ID,
			'ref_type' => 'comment',
		) );
	}

	/**
	 * Award reputation when user recive an up vote.
	 *
	 * @param integer $post_id Post ID.
	 */
	public static function vote_up( $post_id ) {
		$_post = get_post( $post_id );

		if ( get_current_user_id() !== $_post->post_author ) {
			ap_insert_notification( array(
				'user_id'  => $_post->post_author,
				'actor'    => get_current_user_id(),
				'ref_id'   => $_post->ID,
				'ref_type' => $_post->post_type,
				'verb'     => 'vote_up',
			) );
		}
	}

	/**
	 * Notify when user recive an down vote.
	 *
	 * @param integer $post_id Post ID.
	 */
	public static function vote_down( $post_id ) {
		$_post = get_post( $post_id );

		if ( get_current_user_id() !== $_post->post_author ) {
			ap_insert_notification( array(
				'user_id'  => $_post->post_author,
				'actor'    => get_current_user_id(),
				'ref_id'   => $_post->ID,
				'ref_type' => $_post->post_type,
				'verb'     => 'vote_down',
			) );
		}
	}

	/**
	 * Notify when user recive an up vote.
	 *
	 * @param integer $post_id Post ID.
	 */
	public static function undo_vote_up( $post_id ) {
		ap_delete_notifications( array(
			'ref_id' => $post_id,
			'actor'  => get_current_user_id(),
			'verb'   => 'vote_up',
		) );
	}

	/**
	 * Notify when user recive an down vote.
	 *
	 * @param integer $post_id Post ID.
	 */
	public static function undo_vote_down( $post_id ) {
		ap_delete_notifications( array(
			'ref_id' => $post_id,
			'actor'  => get_current_user_id(),
			'verb'   => 'vote_down',
		) );
	}
}

/**
 * Insert table when addon is activated.
 */
function ap_notification_addon_activation() {
	global $wpdb;
	$charset_collate = ! empty( $wpdb->charset ) ? 'DEFAULT CHARACTER SET ' . $wpdb->charset : '';

	$table = 'CREATE TABLE `' . $wpdb->prefix . 'ap_notifications` (
			`noti_id` bigint(20) NOT NULL AUTO_INCREMENT,
			`noti_user_id` bigint(20) NOT NULL,
			`noti_actor` bigint(20) NOT NULL,
			`noti_parent` bigint(20) NOT NULL,
			`noti_ref_id` bigint(20) NOT NULL,
			`noti_ref_type` varchar(100) NOT NULL,
			`noti_verb` varchar(100) NOT NULL,
			`noti_date` timestamp NULL DEFAULT NULL,
			`noti_seen` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`noti_id`)
		)' . $charset_collate . ';';

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $table );
}
ap_addon_activation_hook( 'free/' . basename( __FILE__ ), 'ap_notification_addon_activation' );

// Init class.
AnsPress_Notification_Hook::init();