<?php
$args = wp_json_encode(
  [
    '__nonce' => wp_create_nonce( 'user_info_edit_' . $template_args['user_data']->ID ),
    'id'      => $template_args['user_data']->ID,
    'ap_ajax_action' => 'user_info_edit_password'
  ]
);?>
<div class="ui mini modal ap-user-info-edit-modal --password">
  <div class="header">
    비밀번호 변경
  </div>
  <div class="content"> <?php 
    $args = array(
      'show_links' => false
    );
    $form = tml_get_form( 'lostpassword' );
    $field = $form->get_field( 'redirect_to' );
    $field->set_value( ap_user_link( $template_args['user_data']->ID ) . '/?confirm_email=true' );
    echo $form->render( $args ); ?>
  </div>
</div>