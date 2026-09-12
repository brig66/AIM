<?php
/**
 * Undo engine.
 *
 * Every action that changes settings or touches the database takes a snapshot
 * first. A snapshot holds the complete settings array as it stood before the
 * change, plus — for database edits — the previous post content, comment
 * status and any deleted comments, so a restore puts the site back exactly.
 *
 * Snapshot bodies live in their own options so the index stays small, and
 * post backups live in post meta on the post they belong to.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Snapshots {

	/** Maximum snapshots retained before the oldest are pruned. */
	const MAX_SNAPSHOTS = 60;

	/** Option prefix for a snapshot body. */
	const BODY_PREFIX = 'aim_avt_snapshot_';

	/** Post meta prefix for a per-post backup. */
	const POST_META_PREFIX = '_aim_avt_backup_';

	/**
	 * Create a snapshot of the current settings.
	 *
	 * @param string $type  Machine type, e.g. "settings", "linkguard-clean".
	 * @param string $label Human label shown on the Undo screen.
	 * @param array  $extra Arbitrary extra payload stored with the snapshot.
	 * @return string Snapshot ID.
	 */
	public static function create( $type, $label, array $extra = array() ) {
		$id = self::new_id();

		$body = array(
			'id'        => $id,
			'type'      => (string) $type,
			'label'     => (string) $label,
			'created'   => time(),
			'user'      => self::current_user_label(),
			'settings'  => get_option( AIM_AVT_OPTION, array() ),
			'extra'     => $extra,
			'posts'     => array(), // Post IDs that carry a backup for this snapshot.
			'restored'  => 0,
		);

		update_option( self::BODY_PREFIX . $id, $body, false );

		$index   = self::index();
		$index[] = array(
			'id'      => $id,
			'type'    => (string) $type,
			'label'   => (string) $label,
			'created' => $body['created'],
			'user'    => $body['user'],
			'posts'   => 0,
		);
		self::save_index( $index );
		self::prune();

		return $id;
	}

	/**
	 * Back up a post's revertible fields into a snapshot.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param int    $post_id     Post ID.
	 * @return bool
	 */
	public static function backup_post( $snapshot_id, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		update_post_meta(
			$post_id,
			self::POST_META_PREFIX . $snapshot_id,
			array(
				'post_content'    => $post->post_content,
				'post_excerpt'    => $post->post_excerpt,
				'comment_status'  => $post->comment_status,
				'ping_status'     => $post->ping_status,
			)
		);

		$body = self::body( $snapshot_id );
		if ( $body ) {
			if ( ! in_array( (int) $post_id, $body['posts'], true ) ) {
				$body['posts'][] = (int) $post_id;
				update_option( self::BODY_PREFIX . $snapshot_id, $body, false );
				self::touch_index( $snapshot_id, array( 'posts' => count( $body['posts'] ) ) );
			}
		}
		return true;
	}

	/**
	 * Back up comments that are about to be deleted so they can be put back.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param array  $comments    Array of WP_Comment objects.
	 */
	public static function backup_comments( $snapshot_id, array $comments ) {
		$body = self::body( $snapshot_id );
		if ( ! $body ) {
			return;
		}
		$rows = isset( $body['extra']['comments'] ) ? $body['extra']['comments'] : array();
		foreach ( $comments as $comment ) {
			$rows[] = array(
				'comment_post_ID'      => (int) $comment->comment_post_ID,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => $comment->comment_date_gmt,
				'comment_content'      => $comment->comment_content,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_type'         => $comment->comment_type,
				'comment_parent'       => (int) $comment->comment_parent,
				'user_id'              => (int) $comment->user_id,
			);
		}
		$body['extra']['comments'] = $rows;
		update_option( self::BODY_PREFIX . $snapshot_id, $body, false );
	}

	/**
	 * Back up a file on disk so a snapshot can put it back byte for byte.
	 *
	 * Records absence too: if the file does not exist yet, restoring the
	 * snapshot deletes the one this plugin created.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param string $path        Absolute path.
	 * @return bool
	 */
	public static function backup_file( $snapshot_id, $path ) {
		$body = self::body( $snapshot_id );
		if ( ! $body ) {
			return false;
		}

		$existed = file_exists( $path );
		$contents = null;
		if ( $existed ) {
			$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $contents ) {
				return false;
			}
		}

		$files = isset( $body['extra']['files'] ) ? $body['extra']['files'] : array();
		$files[] = array(
			'path'     => $path,
			'existed'  => $existed,
			'contents' => $existed ? base64_encode( $contents ) : '',
		);
		$body['extra']['files'] = $files;

		update_option( self::BODY_PREFIX . $snapshot_id, $body, false );
		return true;
	}

	/**
	 * Restore a snapshot: settings first, then post content, then comments.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @return array{ok:bool,message:string,details:array}
	 */
	public static function restore( $snapshot_id ) {
		$body = self::body( $snapshot_id );
		if ( ! $body ) {
			return array(
				'ok'      => false,
				'message' => __( 'That restore point no longer exists.', 'aim-avt' ),
				'details' => array(),
			);
		}

		// Take a snapshot of where we are now, so the restore itself is undoable.
		self::create(
			'pre-restore',
			sprintf(
				/* translators: %s: label of the restore point being applied. */
				__( 'Automatic backup taken before restoring "%s"', 'aim-avt' ),
				$body['label']
			)
		);

		$details = array();

		// 1. Settings.
		if ( is_array( $body['settings'] ) ) {
			AIM_AVT_Settings::save( $body['settings'] );
			$details[] = __( 'Settings restored.', 'aim-avt' );
		}

		// 2. Post content.
		$restored_posts = 0;
		foreach ( (array) $body['posts'] as $post_id ) {
			$backup = get_post_meta( $post_id, self::POST_META_PREFIX . $snapshot_id, true );
			if ( ! is_array( $backup ) || ! get_post( $post_id ) ) {
				continue;
			}
			$update = array( 'ID' => (int) $post_id );
			foreach ( array( 'post_content', 'post_excerpt', 'comment_status', 'ping_status' ) as $field ) {
				if ( isset( $backup[ $field ] ) ) {
					$update[ $field ] = $backup[ $field ];
				}
			}
			// Content is being put back verbatim; kses would strip markup the
			// editor legitimately saved, so the raw value is restored. Only
			// re-add the filter if it was there to begin with — a user with
			// unfiltered_html does not have it, and adding it would start
			// stripping their markup on every later save.
			$kses_was_on = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
			if ( false !== $kses_was_on ) {
				remove_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_was_on );
			}
			$result = wp_update_post( wp_slash( $update ), true );
			if ( false !== $kses_was_on ) {
				add_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_was_on );
			}

			if ( ! is_wp_error( $result ) ) {
				$restored_posts++;
			}
		}
		if ( $restored_posts ) {
			$details[] = sprintf(
				/* translators: %d: number of pages or posts. */
				_n( '%d page or post reverted to its previous content.', '%d pages or posts reverted to their previous content.', $restored_posts, 'aim-avt' ),
				$restored_posts
			);
		}

		// 3. Deleted comments.
		$restored_comments = 0;
		if ( ! empty( $body['extra']['comments'] ) && is_array( $body['extra']['comments'] ) ) {
			foreach ( $body['extra']['comments'] as $row ) {
				if ( empty( $row['comment_post_ID'] ) || ! get_post( $row['comment_post_ID'] ) ) {
					continue;
				}
				$new_id = wp_insert_comment( wp_slash( $row ) );
				if ( $new_id ) {
					$restored_comments++;
				}
			}
		}
		if ( $restored_comments ) {
			$details[] = sprintf(
				/* translators: %d: number of comments. */
				_n( '%d deleted comment put back.', '%d deleted comments put back.', $restored_comments, 'aim-avt' ),
				$restored_comments
			);
		}

		// 4. Files on disk.
		$restored_files = 0;
		if ( ! empty( $body['extra']['files'] ) && is_array( $body['extra']['files'] ) ) {
			foreach ( $body['extra']['files'] as $file ) {
				if ( empty( $file['path'] ) ) {
					continue;
				}
				if ( ! empty( $file['existed'] ) ) {
					$written = file_put_contents( $file['path'], base64_decode( $file['contents'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					if ( false !== $written ) {
						$restored_files++;
					}
				} elseif ( file_exists( $file['path'] ) ) {
					if ( wp_delete_file( $file['path'] ) || ! file_exists( $file['path'] ) ) {
						$restored_files++;
					}
				}
			}
		}
		if ( $restored_files ) {
			$details[] = sprintf(
				/* translators: %d: number of files. */
				_n( '%d file on disk restored to its previous contents.', '%d files on disk restored to their previous contents.', $restored_files, 'aim-avt' ),
				$restored_files
			);
		}

		$body['restored'] = time();
		update_option( self::BODY_PREFIX . $snapshot_id, $body, false );

		if ( empty( $details ) ) {
			$details[] = __( 'Nothing needed changing — the site already matched this restore point.', 'aim-avt' );
		}

		AIM_AVT_Log::add(
			'restore',
			sprintf(
				/* translators: %s: restore point label. */
				__( 'Restored "%s".', 'aim-avt' ),
				$body['label']
			)
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: restore point label. */
				__( 'Restored "%s".', 'aim-avt' ),
				$body['label']
			),
			'details' => $details,
		);
	}

	/**
	 * Delete a snapshot and the post meta it owns.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 */
	public static function delete( $snapshot_id ) {
		$body = self::body( $snapshot_id );
		if ( $body ) {
			foreach ( (array) $body['posts'] as $post_id ) {
				delete_post_meta( $post_id, self::POST_META_PREFIX . $snapshot_id );
			}
		}
		delete_option( self::BODY_PREFIX . $snapshot_id );

		$index = array_values(
			array_filter(
				self::index(),
				static function ( $row ) use ( $snapshot_id ) {
					return $row['id'] !== $snapshot_id;
				}
			)
		);
		self::save_index( $index );
	}

	/**
	 * Delete every snapshot. Used by the uninstaller and the "clear history" button.
	 */
	public static function delete_all() {
		foreach ( self::index() as $row ) {
			self::delete( $row['id'] );
		}
		delete_option( AIM_AVT_SNAPSHOT_INDEX );
	}

	/**
	 * The snapshot index, newest first.
	 *
	 * @return array
	 */
	public static function index() {
		$index = get_option( AIM_AVT_SNAPSHOT_INDEX, array() );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * The snapshot index sorted newest first.
	 *
	 * @return array
	 */
	public static function listing() {
		$index = self::index();
		usort(
			$index,
			static function ( $a, $b ) {
				return $b['created'] <=> $a['created'];
			}
		);
		return $index;
	}

	/**
	 * Load a snapshot body.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @return array|false
	 */
	public static function body( $snapshot_id ) {
		$body = get_option( self::BODY_PREFIX . $snapshot_id, false );
		if ( ! is_array( $body ) ) {
			return false;
		}
		$body += array( 'posts' => array(), 'extra' => array(), 'settings' => array(), 'restored' => 0 );
		return $body;
	}

	/**
	 * Update fields on one index row.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param array  $fields      Fields to merge.
	 */
	protected static function touch_index( $snapshot_id, array $fields ) {
		$index = self::index();
		foreach ( $index as $i => $row ) {
			if ( $row['id'] === $snapshot_id ) {
				$index[ $i ] = array_merge( $row, $fields );
			}
		}
		self::save_index( $index );
	}

	/**
	 * Save the index.
	 *
	 * @param array $index Index.
	 */
	protected static function save_index( array $index ) {
		update_option( AIM_AVT_SNAPSHOT_INDEX, array_values( $index ), false );
	}

	/**
	 * Drop the oldest snapshots past the retention limit, but never drop one
	 * that carries a database backup — those are the ones an operator actually
	 * needs, and there will only ever be a handful.
	 */
	protected static function prune() {
		$index = self::listing();
		if ( count( $index ) <= self::MAX_SNAPSHOTS ) {
			return;
		}
		$kept    = 0;
		$to_drop = array();
		foreach ( $index as $row ) {
			$has_db_backup = ! empty( $row['posts'] );
			if ( $has_db_backup ) {
				continue;
			}
			$kept++;
			if ( $kept > self::MAX_SNAPSHOTS ) {
				$to_drop[] = $row['id'];
			}
		}
		foreach ( $to_drop as $id ) {
			self::delete( $id );
		}
	}

	/**
	 * Generate a collision-resistant snapshot ID.
	 *
	 * @return string
	 */
	protected static function new_id() {
		return gmdate( 'Ymd-His' ) . '-' . substr( md5( uniqid( '', true ) ), 0, 6 );
	}

	/**
	 * Describe the acting user for the audit trail.
	 *
	 * @return string
	 */
	protected static function current_user_label() {
		$user = wp_get_current_user();
		if ( $user && $user->exists() ) {
			return $user->user_login;
		}
		return 'system';
	}
}
