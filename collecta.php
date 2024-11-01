<?php
/*
Plugin Name: collecta - WooCommerce Gateway
Description: Extends WooCommerce by Adding the collecta payment gateway.
Version: 1
Author: Ezekiel Fadipe, O'sigla Resources
Author URI: http://www.osigla.com.ng/
*/
if (!defined('ABSPATH')) {
    exit;
}
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}
add_action('plugins_loaded', 'woocommerce_collecta_payment_init', 0);
function woocommerce_collecta_payment_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;
		class WC_collecta_payment extends WC_Payment_Gateway {
			public function __construct() {
					$this->collecta_payment_errors = new WP_Error();

					$this->id = 'collecta';
					$this->medthod_title = 'collectapayment';
					$this->icon = apply_filters('woocommerce_collecta_payment_icon', plugins_url('images/logo.png', __FILE__));
					$this->has_fields = false;

					$this->init_form_fields();
					$this->init_settings();

					$this->title = $this->settings['title'];
					$this->description = $this->settings['description'];
					$this->collecta_handle = $this->settings['collecta_handle'];
                    $this->secret_key 	= $this->settings['secret_key'];
					// $this->wema_tranx_curr = $this->settings['wema_tranx_curr'];
					// $this->hashkey = $this->settings['hashkey'];


							$this->posturl = 'https://www.collecta.com.ng/pay/?i='.$this->collecta_handle;
//							$this->geturl = "https://apps.wemabank.com/wemamerchants/TransactionStatusService.asmx/GetTransactionDetailsJson";



					$this->msg['message'] = "";
					$this->msg['class'] = "";

					if (isset($_REQUEST["ResponseCode"]) && isset($_REQUEST["Message"]) && isset($_REQUEST["Status"]) && isset($_REQUEST["TransactionId"])) {
							$this->check_collecta_response();
					}

					if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
							add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
					} else {
							add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
					}

					add_action('woocommerce_receipt_collecta', array(&$this, 'receipt_page'));
			}
      function receipt_page($order) {
          global $woocommerce;
          $items = $woocommerce->cart->get_cart();
          $item_rows = "";
          $currency = get_woocommerce_currency_symbol();
          $css = "<style>
                  .label-info{
                      background-color: green;
                      color: #f4f4f4;
                      padding: 5px;
                  }
                  tbody tr td { border-bottom: 1px solid; }
                  tbody tr{
                      font-size:14px;
                  }
                  thead{
                      font-size:16px;
                  }
                  tbody tr td{
                      padding: 10px 0px;
                  }tr:nth-child(even) {
                      background-color: #f4f4f4;
                  }
                  tfoot tr td:nth-child(1){
                      font-weight: bold;
                      font-size: 22px;
                      padding: 10px 0px 0px 10px;
                  }
                  </style>";
          echo $css;
          $price_total = 0;

          $order_data = new WC_Order($order);
          $shipping = $order_data->get_shipping_method();
          $shipping_rate = $order_data->get_total_shipping();
          ;
          foreach ($items as $item => $values) {
              $_product = $values['data']->post;
              $price = get_post_meta($values['product_id'], '_price', true) * $values['quantity'];
              $price_total += $price;
              $item_rows .= '<tr><td>' . $_product->post_title . '</td><td>' . $values['quantity'] . '</td><td>' . $price . '</td> </tr>';
          }
          $item_rows .= "<tr><td><b><i>Sub Total</i></b></td><td></td><td>" . $price_total . "</td></tr>";
          if ($shipping && $shipping <> "") {
              $item_rows .= "<tr><td><b><i>Shipping(" . $shipping
                      . ")</i></b></td><td></td><td>" . $shipping_rate
                      . "</td></tr>";
          }
          if ($shipping_rate > 0) {
              $price_total += $shipping_rate;
          }
          $confirmation_table = '<p><span class="label-info">Please review your order then click on "Pay via Collecta" button</span></p>
                                  <br><h2>Your Order</h2>
                                  <table><thead><tr><th>Product</th><th>Qty</th><th>Amount(' . trim($currency) . ')</th></tr><thead>
                                  <tbody>' . $item_rows . '</tbody>
                                  <tfoot><tr><td><b>Grand Total</b></td><td></td><td><b>' . $currency . $price_total .
                  '</b></td></tr></tfoot></table>';

          echo $confirmation_table;
          echo $this->generate_collecta_form($order);
      }
      public function generate_collecta_form($order_id) {
          global $woocommerce;

          $order = new WC_Order($order_id);
          $txnid = $order_id . '_' . date("ymds");

          $redirect_url = $woocommerce->cart->get_checkout_url();
          $collecta_cust_id = $order->billing_email;

          $collecta_hash = $this->collecta_handle. $txnid.$order->order_total .  $this->secret_key;

          $hash = hash('sha512', $collecta_hash);

          $collecta_args = array(
              'URL' => $this->collecta_handle,
              'Amount' => $order->order_total,
              'Email' => $collecta_cust_id,
              'TransactionId' => $txnid,
              'Hash' => $hash,
              'PhoneNumber' =>$order->billing_phone ,
              'PayerName' => $order->billing_first_name. ' ' . $order->billing_last_name ,
              'ReturnURL' => $redirect_url


          );

          $collecta_args_array = array();
          foreach ($collecta_args as $key => $value) {
              $collecta_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
          }
          return '<form action="' . $this->posturl . '" method="post" id="collecta_payment_form">
          ' . implode('', $collecta_args_array) . '
          <input type="submit" class="button-alt" name="checkout" id="submit_collecta_payment_form" value="' . __('Pay via Collecta', 'collecta') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'collecta') . '</a>
          <script type="text/javascript">
function processcollectaJSPayment(){
jQuery("body").block(
      {
          message: "<img src=\"' . plugins_url('assets/images/ajax-loader.gif', __FILE__) . '\" alt=\"redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'collecta') . '",
              overlayCSS:
      {
          background: "#fff",
              opacity: 0.6
  },
  css: {
      padding:        20,
          textAlign:      "center",
          color:          "#555",
          border:         "3px solid #aaa",
          backgroundColor:"#fff",
          cursor:         "wait",
          lineHeight:"32px"
  }
  });
  jQuery("#collecta_payment_form").submit();
  }
  jQuery("#submit_collecta_payment_form").click(function (e) {
      e.preventDefault();
      processcollectaJSPayment();
  });
</script>
          </form>';
      }
      function init_form_fields() {
          $this->form_fields = array(

            'enabled' => array(
                'title' => __('Enable/Disable', 'collecta'),
                'type' => 'checkbox',
                'label' => __('Enable Collecta Payment Module.', 'collecta'),
                'default' => 'no'),

              'title' => array(
                  'title' => __('Title:', 'collecta'),
                  'type' => 'text',
                  'description' => __('This controls the title which the user sees during checkout.', 'collecta'),
                  'default' => __('Collecta Payment Gateway', 'collecta')),
              'description' => array(
                  'title' => __('Description:', 'collecta'),
                  'type' => 'textarea',
                  'description' => __('This controls the description which the user sees during checkout.', 'collecta'),
                  'default' => __('Pay via Collecta', 'collecta')),
              'collecta_handle' => array(
                  'title' => __('Handle', 'collecta'),
                  'type' => 'text',
                  'description' => __('Enter your Handle without the @', 'collecta')),
              'secret_key' => array(
                  'title' => __('Secret Key', 'collecta'),
                  'type' => 'text',
                  'description' => __('Enter your secret key here', 'collecta'))

          );
      }
      public function admin_options() {
          echo '<h3>' . __('Collecta Payment Gateway', 'collecta') . '</h3>';
          echo '<p>' . __('Collecta is most popular payment gateway for online shopping in Nigeria') . '</p>';
          echo '<table class="form-table">';
          $this->generate_settings_html();
          echo '</table>';
          // wp_enqueue_script('gtpay_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
      }
      function payment_fields() {
          if ($this->description)
              echo wpautop(wptexturize($this->description));
      }
      function process_payment($order_id) {
          global $woocommerce;
          $order = new WC_Order($order_id);
          return array(
              'result' => 'success',
              'redirect' => add_query_arg(
                      'order', $order->id, add_query_arg(
                              'key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))
                      )
              )
          );
      }
      function showMessage($content) {
          return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
      }
      function get_pages($title = false, $indent = true) {
          $wp_pages = get_pages('sort_column=menu_order');
          $page_list = array();
          if ($title)
              $page_list[] = $title;
          foreach ($wp_pages as $page) {
              $prefix = '';
              // show indented child pages?
              if ($indent) {
                  $has_parent = $page->post_parent;
                  while ($has_parent) {
                      $prefix .= ' - ';
                      $next_page = get_page($has_parent);
                      $has_parent = $next_page->post_parent;
                  }
              }
              // add to page list array array
              $page_list[$page->ID] = $prefix . $page->post_title;
          }
          return $page_list;
      }
      function check_collecta_response() {
          global $woocommerce;
          $collecta_echo_data = $_REQUEST["TransactionId"];
          $data = explode("_", $collecta_echo_data);
          $wc_order_id = $data[0];
          $order = new WC_Order($wc_order_id);

          try {
                  wc_print_notices();
//                  $mert_id = $this->wema_mert_id;
//                  $ch = curl_init();
//                  $url = $this->geturl. "?merchantcode={$mert_id}&transactionref={$wema_echo_data}";
//
//                  curl_setopt_array($ch, array(
//                      CURLOPT_URL => $url,
//                      CURLOPT_NOBODY => false,
//                      CURLOPT_RETURNTRANSFER => true,
//                      CURLOPT_SSL_VERIFYPEER => false
//                  ));
//                  $response = curl_exec($ch);
//                  $d1 = new SimpleXMLElement($response);
//                  $json = json_decode($d1, TRUE);

                  $respond_code = $_REQUEST['Status'];
                  
                  if ($respond_code == "1") {
                      #payment successful
                      $respond_desc = $_REQUEST['Message'];
                      $message_resp = "Approved Successful." .
                          "<br>" . $respond_desc .
                          "<br>Transaction Reference: " . $collecta_echo_data;
                      $message_type = "success";
                      $order->payment_complete();
                      $order->update_status('completed');
                      $order->add_order_note('Collecta payment successful: ' . $message_resp);
                      $woocommerce->cart->empty_cart();
                      $redirect_url = $this->get_return_url($order);

                      wc_add_notice($message_resp, "success");
                  } else {
                      #payment failed
                      $respond_desc = $_REQUEST['Message'];
                      $message_resp = "Your transaction was not successful." .
                          "<br>Reason: " . $respond_desc .
                          "<br>Transaction Reference: " .  $collecta_echo_data;
                      $message_type = "error";
                      $order->add_order_note('collecta payment failed: ' . $message_resp);
                      $order->update_status('cancelled');
                      $redirect_url = $order->get_cancel_order_url();
                      wc_add_notice($message_resp, "error");
                  }


              $notification_message = array(
                  'message' => $message_resp,
                  'message_type' => $message_type
              );

              wp_redirect(html_entity_decode($redirect_url));
              exit;
          } catch (Exception $e) {
              $order->add_order_note('Error: ' . $e->getMessage());

              wc_add_notice($e->getMessage(), "error");
              $redirect_url = $order->get_cancel_order_url();
              wp_redirect(html_entity_decode($redirect_url));
              exit;
          }

      }
      static function woocommerce_add_collecta_gateway($methods) {
          $methods[] = 'WC_collecta_payment';
          return $methods;
      }
      static function woocommerce_add_collecta_settings_link($links) {
          $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=collecta">Settings</a>';
          array_unshift($links, $settings_link);
          return $links;
      }






		}
    $plugin = plugin_basename(__FILE__);

    add_filter("plugin_action_links_$plugin", array('WC_collecta_payment', 'woocommerce_add_collecta_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_collecta_payment', 'woocommerce_add_collecta_gateway'));



			}
