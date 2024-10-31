<?php
/**
 * Plugin Name:  Send message to Google Chat for CF7
 * Description:  Contact Form 7 Add-on - Send message to google chat.
 * Version:      1.0.0
 * Text Domain:  send-message-google-chat-cf7
 * Author:       Codingharmony
 * Contributors: codingharmony
 * Requires at least: 4.0
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Contact Form 7 Google Chat
 * @category Contact Form 7 Addon
 * @author Codingharmony
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main WPCF7_Google_Chat Class
 */

class WPCF7_Google_Chat {


    /**
     * Construct class
     */
    public function __construct() {
        $this->plugin_url       = plugin_dir_url( __FILE__ );
        $this->plugin_path      = plugin_dir_path( __FILE__ );
        $this->version          = '1.0';
        $this->add_actions();
    }

    /**
     * Add actions
     */
    private function add_actions() {
        add_action( 'wpcf7_editor_panels', array( $this, 'add_panel' ) );
        add_action( 'wpcf7_after_save', array( $this, 'store_meta' ) );
        add_action('wpcf7_mail_sent', array($this, 'send_to_google_chat'));
    }

    /**
     * Validate and store meta data
     *
     * @param object $contact_form WPCF7_ContactForm Object - All data that is related to the form.
     */
    public function store_meta( $contact_form ) {
        if (empty( $_POST )) {
            return;
        } else {
            if (! isset( $_POST['wpcf7_google_chat_page_metaboxes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ($_POST['wpcf7_google_chat_page_metaboxes_nonce'])), 'wpcf7_google_chat_page_metaboxes' ) ) {
                return;
            }

            $form_id = $contact_form->id();
            $fields = $this->get_plugin_fields( $form_id );
            if ( isset( $_POST['wpcf7-goolge-chat'] ) && is_array( $_POST['wpcf7-goolge-chat'] ) ) {
                $data = $_POST['wpcf7-goolge-chat'];

                foreach ( $fields as $field ) {
                    $value = $data[$field['name']] ?? '';

                    if ($field['type'] == 'text') {
                        $value = sanitize_text_field($value);
                    }

                    update_post_meta( $form_id, '_wpcf7_google_chat_' . $field['name'], $value );
                }
            }
        }
    }

    /**
     * Adds a tab on contact form edit page
     *
     * @param array $panels an array of panels.
     */
    public function add_panel( $panels ) {
        $panels['google-chat-panel'] = array(
            'title'     => __( 'Google Chat Settings', 'send-message-google-chat-cf7' ),
            'callback'  => array( $this, 'create_panel_inputs' ),
        );
        return $panels;
    }

    /**
     * Create plugin fields
     *
     * @return array of plugin fields: name and type
     */
    public function get_plugin_fields() {
        $fields = array(
            array(
                'name' => 'webhook_url',
                'type' => 'text',
            ),
        );

        return $fields;
    }

    /**
     * Get all fields values
     *
     * @param integer $post_id Form ID.
     * @return array of fields values keyed by fields name
     */
    public function get_fields_values( $post_id ) {
        $fields = $this->get_plugin_fields();

        foreach ( $fields as $field ) {
            $values[ $field['name'] ] = get_post_meta( $post_id, '_wpcf7_google_chat_' . $field['name'] , true );
        }

        return $values;
    }

    /**
     * Create the panel inputs
     *
     * @param  object $post Post object.
     */
    public function create_panel_inputs( $post ) {
        wp_nonce_field( 'wpcf7_google_chat_page_metaboxes', 'wpcf7_google_chat_page_metaboxes_nonce' );
        $fields = $this->get_fields_values( $post->id() );
        ?>
        <fieldset>
            <legend><?php esc_html_e( 'Google Chat Settings', 'send-message-google-chat-cf7' );?></legend>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="wpcf7-google-chat-webhook-url"><?php esc_html_e( 'Webhook url', 'send-message-google-chat-cf7' );?></label>
                    </th>
                    <td>
                        <input type="text" id="wpcf7-google-chat-webhook-url" class="large-text" placeholder="<?php esc_html_e( 'Webhook Url', 'send-message-google-chat-cf7' );?>" name="wpcf7-goolge-chat[webhook_url]" value="<?php echo esc_html($fields['webhook_url']);?>">
                    </td>
                </tr>
                </tbody>
            </table>
        </fieldset>
        <?php
    }

    public function send_to_google_chat($contact_form)
    {
        $form_id = $contact_form->id();
        $webhook_url = get_post_meta($form_id, '_wpcf7_google_chat_webhook_url', true);


        if (!$webhook_url) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();

        if ($submission) {

            $posted_data = $submission->get_posted_data();
            $uploaded_files = $submission->uploaded_files();

            $message = "New message from contact form:\n";
            foreach ($posted_data as $key => $value) {
                if(strpos($key, 'file') === 0){
                    continue;
                }
                $message .= ucfirst($key) . ": " . $value . "\n";
            }

            foreach ($uploaded_files as $field_name => $uploaded_file) {
                $message .= "File: " . basename($uploaded_file[0]) . "\n";
            }

            $payload = json_encode(array('text' => $message));

            $response = wp_remote_post($webhook_url, array(
                'method'    => 'POST',
                'body'      => $payload,
                'headers'   => array('Content-Type' => 'application/json')
            ));

        }
    }

}

$cf7_google_chat = new WPCF7_Google_Chat();