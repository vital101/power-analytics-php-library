<?php
//
// WP Power Analytics
// v1.2.1
//
if (! class_exists('WordPressPowerAnalytics')) {

    class WordPressPowerAnalytics {
        private $productUUID;
        private $absolutePath;
        private $slug;
        private $events = array();
        private $sessionUUID;
        private $sessionStartTime;

        function __construct($productUUID, $absolutePath, $slug) {
            $this->productUUID = $productUUID;
            $this->absolutePath = $absolutePath;
            $this->slug = $slug;
        }

        function __destruct() {
            if (count($this->events) > 0) {
                $this->send_events();
            }
        }

        public function initialize() {
            $transientKey = $this->get_transient_key();
            if ($this->should_send_analytics_data($transientKey)) {
                $dataToSend = $this->get_analytics_data();
                $this->send_data($dataToSend);
            }

            $this->sessionUUID = $this->get_or_create_session();
        }

        public function track($eventName, $eventValue) {
            $now = new DateTime();
            $now->setTimezone(new DateTimeZone('America/New_York'));
            $evt = array(
                "name" => $eventName,
                "value" => $eventValue,
                "created" => $now->format('Y-m-d H:i:sP')
            );
            array_push($this->events, $evt);
        }

        private function get_analytics_data() {
            global $wp_version;
            return array(
                "date" => $this->get_date(),
                "product_uuid" => $this->productUUID,
                "product_type" => $this->get_product_type(),
                "product_version" => $this->get_installed_version(),
                "wordpress_version" => $wp_version,
                "language" => $this->get_language(),
                "php_version" => $this->get_php_version(),
                "mysql_version" => $this->get_mysql_version(),
                "domain" => $this->get_domain(),
                "installed_plugins" => $this->get_all_plugins(),
                "installed_theme" => $this->get_theme()
            );
        }

        private function get_date() {
            return date("Y-m-d");
        }

        private function get_product_type() {
            if (strpos($this->absolutePath, "wp-content/themes") !== false) {
                return "theme";
            }

            if (strpos($this->absolutePath, "wp-content/plugins") !== false) {
                return "plugin";
            }

            return "unknown";
        }

        private function get_or_create_session() {
            $key = $this->get_transient_key() . "-events";
            $transientData = get_transient($key);
            if ($transientData) {
                $data = json_decode($transientData, true);
                $this->sessionStartTime = $data["sessionStartTime"];
                return $data["uuid"];
            } else {
                $uuid = $this->get_uuid();
                $now = new DateTime();
                $now->setTimezone(new DateTimeZone('America/New_York'));
                $data = array(
                    "uuid" => $uuid,
                    "sessionStartTime" => $now->format('Y-m-d H:i:sP')
                );
                set_transient($key, json_encode($data), 60 * 10);
                $this->sessionStartTime = $data["sessionStartTime"];
                return $uuid;
            }
        }

        private function get_transient_key() {
            return "wp-power-analytics-{$this->productUUID}";
        }

        private function should_send_analytics_data($transientKey) {
            if (get_transient($transientKey) === 'exists') {
                return false;
            } else {
                $sixHours = 21600;
                set_transient($transientKey, 'exists', $sixHours);
                return true;
            }
        }

        private function send_data($data) {
            $endpoint = 'https://wp-poweranalytics.com/ingest/v1';
            $body = wp_json_encode($data);
            $options = [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 5,
                'redirection' => 5,
                'blocking' => false,
                'httpversion' => '1.0',
                'sslverify' => false,
                'data_format' => 'body',
            ];
            wp_remote_post($endpoint, $options);
        }

        private function send_events() {
            $data = array(
                "session" => array(
                    "uuid" => $this->sessionUUID,
                    "start" => $this->sessionStartTime
                ),
                "product_uuid" => $this->productUUID,
                "events" => $this->events
            );
            $endpoint = 'https://wp-poweranalytics.com/ingest/v1/events';
            $body = wp_json_encode($data);
            $options = [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 5,
                'redirection' => 5,
                'blocking' => false,
                'httpversion' => '1.0',
                'sslverify' => false,
                'data_format' => 'body',
            ];
            wp_remote_post($endpoint, $options);
        }

        private function get_domain() {
			try {
				$urlParts = parse_url(get_site_url());
				$domain = $urlParts['host'];
			} catch(Exception $err) {
				$domain = '';
			}
			return $domain;
		}

		private function get_php_version() {
			try {
				$phpVersion = phpversion();
			} catch(Exception $err) {
				$phpVersion = '';
			}
			return $phpVersion;
		}

		private function get_language() {
			try {
				$language = get_bloginfo('language');
			} catch(Exception $err) {
				$language = '';
			}
			return $language;
		}

		private function get_all_plugins() {
			try {
                if ( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $allPlugins = get_plugins();
                $plugins = array();
                foreach ($allPlugins as $pluginPath => $value) {
                    $data = array([
                        "slug" => $pluginPath,
                        "name" => $value["Name"],
                        "version" => $value["Version"]
                    ]);
                    array_push($plugins, $data);
                }
			} catch(Exception $err) { }
			return $plugins;
		}

		private function get_theme() {
			try {
				$theme = wp_get_theme();
				$name = $theme->get('Name');
				$slug = sanitize_title($name);
				$version = $theme->get('Version');
				return array(
                    "slug" => $slug,
                    "name" => $name,
                    "version" => $version
                );
			} catch (Exception $err) {
				return array();
			}
		}

        private function get_installed_version() {
            $pluginHeader = $this->get_plugin_header();
            if (isset($pluginHeader['Version'])) {
                return $pluginHeader['Version'];
            } else {
                return 'Not Set';
            }
        }

        private function get_plugin_header() {
            if ( !function_exists('get_plugin_data') ) {
				require_once(ABSPATH . '/wp-admin/includes/plugin.php');
			}
			return get_plugin_data($this->absolutePath, false, false);
        }

        private function get_mysql_version() {
            global $wpdb;
            $results = $wpdb->get_results('SELECT VERSION() as version');
            return $results[0]->version;
        }

        private function get_uuid() {
            $data = PHP_MAJOR_VERSION < 7 ? openssl_random_pseudo_bytes(16) : random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // Set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // Set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

    }

}

?>