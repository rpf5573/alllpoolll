<?php
/**
 * Holds all hooks related to AnsPress forms.
 *
 * @link         https://anspress.io
 * @since        4.1.0
 * @license      GPL-3.0+
 * @package      AnsPress
 * @subpackage   Form Hooks
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The form hooks.
 *
 * @since 4.1.0
 */
class AP_Form_Hooks {
	private static $form;

	/**
	 * Register question form.
	 *
	 * @return array
	 * @since 4.1.0
	 * @since 4.1.5 Fixed: anonymous_name field value.
	 *
	 * @category haveTests
	 */
	public static function question_form() {
		$editing    = false;
		$editing_id = ap_sanitize_unslash( 'id', 'r' );

		$form = array(
			'submit_label' => __( 'Submit Question', 'anspress-question-answer' ),
			'fields'       => array(
				'post_title'   => array(
					'type'       => 'input',
					'label'      => __( 'Title', 'anspress-question-answer' ),
					'desc'       => __( 'Question in one sentence', 'anspress-question-answer' ),
					'attr'       => array(
						'autocomplete'   => 'off',
						'placeholder'    => __( 'Question title', 'anspress-question-answer' ),
						'data-loadclass' => 'q-title',
					),
					'min_length' => ap_opt( 'minimum_qtitle_length' ),
					'max_length' => 100,
					'validate'   => 'required,min_string_length,max_string_length,badwords',
					'order'      => 2,
				),
				'post_content' => array(
					'type'        => 'editor',
					'label'       => __( 'Description', 'anspress-question-answer' ),
					'min_length'  => ap_opt( 'minimum_question_length' ),
					'validate'    => 'required,min_string_length,badwords',
					'editor_args' => array(
						'quicktags' => ap_opt( 'question_text_editor' ) ? true : false,
					),
        ),
			),
		);

		$form['fields']['post_id'] = array(
			'type'     => 'input',
			'subtype'  => 'hidden',
			'value'    => $editing_id,
			'sanitize' => 'absint',
		);

		// Set post parent field and nonce.
		$post_parent = ap_isset_post_value( 'post_parent', false );
		if ( $post_parent && wp_verify_nonce( ap_isset_post_value( '__nonce_pp' ), 'post_parent_' . $post_parent ) ) {
			$form['hidden_fields'] = array(
				[
					'name'  => 'post_parent',
					'value' => $post_parent,
				],
				[
					'name'  => '__nonce_pp',
					'value' => wp_create_nonce( 'post_parent_' . $post_parent ),
				],
			);
		}

		// Add value when editing post.
		if ( ! empty( $editing_id ) ) {
			$question = ap_get_post( $editing_id );

			$form['editing']                         = true;
			$form['editing_id']                      = $editing_id;
			$form['submit_label']                    = __( 'Update Question', 'anspress-question-answer' );
			$form['fields']['post_title']['value']   = $question->post_title;
      $form['fields']['post_content']['value'] = $question->post_content;
		}

		/**
		 * Filter for modifying question form `$args`.
		 *
		 * @param   array $fields   Ask form fields.
		 * @param   bool    $editing    Currently editing form.
		 * @since   4.1.0
		 */
		$form = apply_filters( 'ap_question_form_fields', $form, $editing );

		return $form;
	}

	/**
	 * Register answer form.
	 *
	 * @return array
	 * @since 4.1.0
	 * @since 4.1.6 Fixed: editing answer creates new answer.
	 */
	public static function answer_form() {
		$editing     = false;
		$editing_id  = ap_sanitize_unslash( 'id', 'r' );
		$question_id = ap_sanitize_unslash( 'question_id', 'r', get_question_id() );

		$form = array(
			'submit_label' => __( 'Post Answer', 'anspress-question-answer' ),
			'fields'       => array(
				'post_content' => array(
					'type'        => 'editor',
					'label'       => __( 'Description', 'anspress-question-answer' ),
					'min_length'  => ap_opt( 'minimum_ans_length' ),
					'validate'    => 'required,min_string_length,badwords',
					'editor_args' => array(
						'quicktags' => false,
					),
				),
			),
		);

		$form['fields']['post_id'] = array(
			'type'     => 'input',
			'subtype'  => 'hidden',
			'value'    => $editing_id,
			'sanitize' => 'absint',
		);

		// Add value when editing post.
		if ( ! empty( $editing_id ) ) {
			$form['editing']      = true;
			$form['editing_id']   = $editing_id;
			$form['submit_label'] = __( 'Update Answer', 'anspress-question-answer' );
		}

		/**
		 * Filter for modifying answer form `$args`.
		 *
		 * @param   array $fields   Answer form fields.
		 * @param   bool    $editing    Currently editing form.
		 * @since   4.1.0
		 */
		return apply_filters( 'ap_answer_form_fields', $form, $editing );
	}

	/**
	 * Process question form submission.
	 *
	 * @param boolean $manual Is form submitted manually.
	 * @return void|WP_Error|integer This method will not die script if `$manual` is set to `true` and also return `WP_Error` on error.
	 * @since 4.1.0
	 * @since 4.1.5 Added new argument `$manual` for allowing form to be submitted manually.
	 */
	public static function submit_question_form( $manual = false ) {
		$editing = false;

		$form    = anspress()->get_form( 'question' );

		/**
		 * Action triggered before processing question form.
		 *
		 * @since 4.1.0
		 */
		do_action( 'ap_submit_question_form' );

		$values = $form->get_values();

		// Store current values in session. // 이거 타이밍이 별로인데;;
		$form->save_values_session();

		// Check nonce and is valid form. Do not check if `$manual` is true.
		if ( ! $form->is_submitted() && false === $manual ) {
			ap_ajax_json(
				[
					'success'  => false,
					'snackbar' => [ 'message' => __( 'Trying to cheat?!', 'anspress-question-answer' ) ],
				]
			);
		}

		$question_args = array(
			'post_title'   => $values['post_title']['value'],
			'post_content' => $values['post_content']['value'],
		);

		if ( ! empty( $values['post_id']['value'] ) ) {
			$question_args['ID'] = $values['post_id']['value'];
			$editing             = true;
			$_post               = ap_get_post( $question_args['ID'] );

			// Check if valid post type and user can edit.
			if ( false !== $manual && ( 'question' !== $_post->post_type || ! ap_user_can_edit_question( $_post ) ) ) {
				ap_ajax_json( 'something_wrong' );
			}
		}

		// Add default arguments if not editing.
		if ( ! $editing ) {
			$question_args = wp_parse_args(
				$question_args, array(
					'post_author'    => get_current_user_id(),
          'post_name'      => '',
          'post_status'    => 'publish' // direct publish
				)
			);
		}

		if ( $form->have_errors() ) {
			if ( false === $manual ) {
				ap_ajax_json(
					[
						'success'       => false,
						'snackbar'      => [ 'message' => __( 'Unable to post question.', 'anspress-question-answer' ) ],
						'form_errors'   => $form->errors,
						'fields_errors' => $form->get_fields_errors(),
					]
				);
			} else {
				return new WP_Error( 'failed', __( 'Failed to insert question', 'anspress-question-answer' ) );
			}
		}

		// Set post parent.
		$post_parent = ap_sanitize_unslash( 'post_parent', 'r' );
		if ( ! empty( $post_parent ) && wp_verify_nonce( ap_sanitize_unslash( '__nonce_pp', 'r' ), 'post_parent_' . $post_parent ) ) {
			$question_args['post_parent'] = (int) $post_parent;
		}

		/**
		 * Filter question description before saving.
		 *
		 * @param string $content Post content.
		 * @since unknown
		 * @since @3.0.0 Moved from process-form.php
		 */
		$question_args['post_content'] = apply_filters( 'ap_form_contents_filter', $question_args['post_content'] );

		$question_args['post_name'] = $question_args['post_title'];

		if ( $editing ) {
			/**
			 * Can be used to modify `$args` before updating question
			 *
			 * @param array $question_args Question arguments.
			 * @since 2.0.1
			 * @since 4.1.0 Moved from includes/ask-form.php.
			 */
			$question_args = apply_filters( 'ap_pre_update_question', $question_args );
		} else {
			/**
			 * Can be used to modify args before inserting question
			 *
			 * @param array $question_args Question arguments.
			 * @since 2.0.1
			 * @since 4.1.0 Moved from includes/ask-form.php.
			 */
			$question_args = apply_filters( 'ap_pre_insert_question', $question_args );
		}

		if ( ! $editing ) {
			$question_args['post_type'] = 'question';
			$post_id                    = wp_insert_post( $question_args, true );
		} else {
			$post_id = wp_update_post( $question_args, true );
		}

		// If error return and send error message.
		if ( is_wp_error( $post_id ) ) {
			if ( false === $manual ) {
				ap_ajax_json(
					[
						'success'  => false,
						'snackbar' => array(
							'message' => sprintf(
								// Translators: placeholder contain error message.
								__( 'Unable to post question. Error: %s', 'anspress-question-answer' ),
								$post_id->get_error_message()
							),
						),
					]
				);
			} else {
				return $post_id;
			}
		}

		$form->after_save(
			false, array(
				'post_id' => $post_id,
			)
		);

		// Clear temporary images.
		if ( $post_id ) {
			ap_clear_unattached_media();
		}

		/**
		 * Action called after processing question form. This is triggred
		 * only for question submitted from frontend.
		 *
		 * @param integer $post_id Question id just created /updated.
		 *
		 * @since 4.1.11
		 */
		do_action( 'ap_after_question_form_processed', $post_id );

		if ( isset( $question_args['ID'] ) ) {
			$message = __( 'Question updated successfully, you\'ll be redirected in a moment.', 'anspress-question-answer' );
		} else {
			$message = __( 'Your question is posted successfully, you\'ll be redirected in a moment.', 'anspress-question-answer' );
		}

		if ( false === $manual ) {
			anspress()->session->set_question( $post_id );

			ap_ajax_json(
				array(
					'success'  => true,
					'snackbar' => [
						'message' => $message,
					],
					'redirect' => get_permalink( $post_id ),
					'post_id'  => $post_id,
				)
			);
		}

		return $post_id;
	}

	/**
	 * Process question form submission.
	 *
	 * @param boolean $manual Is form submitted manually.
	 * @return void|WP_Error|integer This method will not die script if `$manual` is set to `true` and also return `WP_Error` on error.
	 * @since 4.1.0
	 * @since 4.1.5 Added new argument `$manual` for allowing form to be submitted manually.
	 */
	public static function submit_answer_form( $manual = false ) {
		$editing     = false;
		$question_id = ap_sanitize_unslash( 'question_id', 'r' );
		$form        = anspress()->get_form( 'answer' );

		/**
		 * Action triggered before processing answer form.
		 *
		 * @since 4.1.0
		 */
		do_action( 'ap_submit_answer_form' );

		$values = $form->get_values();
		// Store current values in session.
		$form->save_values_session( $question_id );

		// Check nonce and is valid form.
		if ( false === $manual && ( ! $form->is_submitted() || ! ap_user_can_answer( $question_id ) ) ) {
			ap_ajax_json(
				[
					'success'  => false,
					'snackbar' => [ 'message' => __( 'Trying to cheat?!', 'anspress-question-answer' ) ],
				]
			);
		}

		$answer_args = array(
			'post_title'   => $question_id,
			'post_name'    => $question_id,
			'post_content' => $values['post_content']['value'],
			'post_parent'  => $question_id,
		);

		if ( ! empty( $values['post_id']['value'] ) ) {
			$answer_args['ID'] = $values['post_id']['value'];
			$editing           = true;
			$_post             = ap_get_post( $answer_args['ID'] );

			// Check if valid post type and user can edit.
			if ( 'answer' !== $_post->post_type || ! ap_user_can_edit_answer( $_post ) ) {
				ap_ajax_json( 'something_wrong' );
			}
		}

		// Add default arguments if not editing.
		if ( ! $editing ) {
			$answer_args = wp_parse_args(
				$answer_args, array(
					'post_author'    => get_current_user_id(),
					'post_name'      => '',
					'comment_status' => 'close',
				)
			);
		}

		// Post status.
		$answer_args['post_status'] = 'publish';

		if ( $form->have_errors() ) {
			if ( false === $manual ) {
				ap_ajax_json(
					[
						'success'       => false,
						'snackbar'      => [ 'message' => __( 'Unable to post answer.', 'anspress-question-answer' ) ],
						'form_errors'   => $form->errors,
						'fields_errors' => $form->get_fields_errors(),
					]
				);
			} else {
				return new WP_Error( 'failed', __( 'Please check field', 'anspress-question-answer' ) );
			}
		}

		/**
		 * Filter question description before saving.
		 *
		 * @param string $content Post content.
		 * @since unknown
		 * @since @3.0.0 Moved from process-form.php
		 */
		$answer_args['post_content'] = apply_filters( 'ap_form_contents_filter', $answer_args['post_content'] );

    $answer_args['post_name'] = $answer_args['post_title'];
    
     

		if ( $editing ) {
			/**
			 * Can be used to modify `$args` before updating answer
			 *
			 * @param array $answer_args Answer arguments.
			 * @since 2.0.1
			 * @since 4.1.0 Moved from includes/answer-form.php.
			 */
			$answer_args = apply_filters( 'ap_pre_update_answer', $answer_args );
		} else {
			/**
			 * Can be used to modify args before inserting answer
			 *
			 * @param array $answer_args Answer arguments.
			 * @since 2.0.1
			 * @since 4.1.0 Moved from includes/answer-form.php.
			 */
			$answer_args = apply_filters( 'ap_pre_insert_answer', $answer_args );
		}

		if ( ! $editing ) {
			$answer_args['post_type'] = 'answer';
			$post_id                  = wp_insert_post( $answer_args, true );
		} else {
			$post_id = wp_update_post( $answer_args, true );
		}

		// If error return and send error message.
		if ( is_wp_error( $post_id ) ) {
			if ( false === $manual ) {
				ap_ajax_json(
					[
						'success'  => false,
						'snackbar' => array(
							'message' => sprintf(
								// Translators: placeholder contain error message.
								__( 'Unable to post answer. Error: %s', 'anspress-question-answer' ),
								$post_id->get_error_message()
							),
						),
					]
				);
			} else {
				return $post_id;
			}
		}

		$post = ap_get_post( $post_id );

		$form->delete_values_session( $question_id );

		$form->after_save(
			false, array(
				'post_id' => $post_id,
			)
		);

		// Clear temporary images.
		if ( $post_id ) {
			ap_clear_unattached_media();
		}

		/**
		 * Action called after processing answer form. This is triggered
		 * only for answer submitted from frontend.
		 *
		 * @param integer $post_id Answer id just created /updated.
		 *
		 * @since 4.1.11
		 */
		do_action( 'ap_after_answer_form_processed', $post_id );

		if ( ! $editing ) {
			anspress()->session->set_answer( $post_id );
			ap_answer_post_ajax_response( $question_id, $post_id );
		}

		if ( $editing ) {
			$message = __( 'Answer updated successfully. Redirecting you to question page.', 'anspress-question-answer' );
		} else {
			$message = __( 'Your answer is posted successfully.', 'anspress-question-answer' );
		}

		if ( false === $manual ) {
			ap_ajax_json(
				array(
					'success'  => true,
					'snackbar' => [
						'message' => $message,
					],
					'redirect' => get_permalink( $question_id ),
					'post_id'  => $post_id,
				)
			);
		}

		return $post_id;
	}

	/**
	 * Callback for image field in `image_upload` form.
	 *
	 * @param array $values Values.
	 * @param \AnsPress\Field\Upload $field AnsPress field object.
	 * @return array
	 * @since 4.1.8
	 */
	public static function image_upload_save( $values, $field ) {
		$field->save_uploads();
		// Set files in session, so that it can be validated while saving post.
		if ( ! empty( $field->uploaded_files ) ) {
			foreach ( $field->uploaded_files as $new ) {
				anspress()->session->set_file( $new );
			}
		}

		$the_urls = $field->get_uploaded_files_url();

		return $the_urls;
	}

	/**
	 * Image upload form.
	 *
	 * This form is used for uploading images in AnsPress.
	 *
	 * @return void
	 * @since 4.1.8
	 */
	public static function image_upload_form() {
		return array(
			'submit_label' => __( 'Upload & insert', 'anspress-question-answer' ),
			'fields' => array(
				'image' => array(
					'label' => __( 'Image', 'anspress-question-answer' ),
					'desc'  => __( 'Select image(s) to upload. Only .jpg, .png and .gif files allowed.', 'anspress-question-answer' ),
					'type'  => 'upload',
					'save'  => [ __CLASS__, 'image_upload_save' ],
					'upload_options' => array(
						'multiple'  => false,
						'max_files' => 1,
						'allowed_mimes' => array(
							'jpg|jpeg' => 'image/jpeg',
							'gif'      => 'image/gif',
							'png'      => 'image/png',
						),
					),
					'validate' => 'required',
				),
			)
		);
	}

	/**
	 * Sanitize post description
	 *
	 * @param	string $contents Post content.
	 * @return string					 Return sanitized post content.
	 */
	public static function sanitize_description( $contents ) {
		$contents = ap_trim_traling_space( $contents );
		return $contents;
	}

}