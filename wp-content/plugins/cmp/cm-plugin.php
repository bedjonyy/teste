<?php

/**
 * Plugin Name: Contact Management Plugin
 * Description: A plugin to manage people and their contacts.
 * Version: 1.0
 * Author: Edson Correia
 */

if (!defined('ABSPATH')) {
    exit;
}

class ContactManagement
{

    function __construct()
    {
        add_action('init', array($this, 'custom_post_types'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_shortcode('list_people', 'list_people_shortcode');

    }


    function activate()
    {
        // Placeholder for activation logic
    }

    function deactivate()
    {
        // Placeholder for deactivation logic
    }

    function uninstall()
    {
        // Placeholder for uninstallation logic
    }

    function custom_post_types()
    {

    }

    function add_admin_menu()
    {
        // Add a top-level menu
        add_menu_page(
            'Contact Management', // page title
            'Contact Management', // menu title
            'manage_options', // capability
            'contact-management', // menu slug
            array($this, 'contact_management_page'), // function to display the page
            'dashicons-admin-users', // icon URL
            6 // position
        );

        add_submenu_page(
            'contact-management', // parent slug
            'Add New Person', // page title
            'Add New Person', // menu title
            'manage_options', // capability
            'contact-management-add-person', // menu slug
            array($this, 'add_new_person_page') // function to display the page
        );


        add_submenu_page(
            'contact-management', 
            'Add New Contact', 
            'Add New Contact', 
            'manage_options', 
            'contact-management-add-contact', 
            array($this, 'new_contact_page') 
        );

        add_submenu_page(
            'contact-management', 
            'Person Details', 
            'Person Details', 
            'manage_options', 
            'contact-management-edit-person', 
            array($this, 'edit_person_page') 
        );
    }

    function people_contacts_create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}person` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `deleted` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
      ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}contacts` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `person_id` INT NOT NULL,
        `country_code` VARCHAR(5) NOT NULL,
        `number` VARCHAR(9) NOT NULL UNIQUE,
        `deleted` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        FOREIGN KEY (person_id) REFERENCES {$wpdb->prefix}person(id),
      ) $charset_collate;";

        $wpdb->query($sql);
    }


    function contact_management_page()
    {
        global $wpdb;

        // Debug the main page content
        // $wpdb->show_errors();
        // $wpdb->print_error();

        echo '<h1>Contact Management</h1>';
        echo '<p>Welcome to the Contact Management plugin.</p>';


        echo '<a href="' . admin_url('admin.php?page=contact-management-add-person') . '" class="button">Add New Person</a>';

        // Fetch the list of persons using a custom SQL query
        $query = "SELECT * FROM {$wpdb->prefix}person WHERE deleted = 0";
        $persons = $wpdb->get_results($query);

        if (!empty($persons)) {
            // Start the table
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th scope="col">ID</th>';
            echo '<th scope="col">Name</th>';
            echo '<th scope="col">Email</th>';
            echo '<th scope="col">Action</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            // Loop to person and display their information
            foreach ($persons as $person) {
                echo '<tr>';
                echo '<td>' . $person->id . '</td>';
                echo '<td>' . $person->name . '</td>';
                echo '<td>' . $person->email . '</td>';
                echo '<td>';
                // Link to create contact 
                echo '<a href="' . admin_url('admin.php?page=contact-management-add-contact&person_id=' . $person->id) . '">Add Contact</a> | ';
                // Link to edit the person
                echo '<a href="' . admin_url('admin.php?page=contact-management-edit-person&person_id=' . $person->id) . '">Edit</a> | ';
                // Button to delete the person
                echo '<form method="post" action="" style="display:inline;">';
                echo '<input type="hidden" name="action" value="delete_person">';
                echo '<input type="hidden" name="person_id" value="' . $person->id . '">';
                echo '<input type="submit" value="Delete">';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No persons found.</p>';
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete_person') {
            // Sanitize the input
            $person_id = intval($_POST['person_id']);

            $this->soft_delete_person($person_id);

            echo '<p>Person has been deleted.</p>';
            wp_redirect(admin_url('admin.php?page=contact-management'));
            exit;
        }
    }

    /* Add new person */

    function add_new_person_page()
    {

        // Form for adding a new person
        echo '<h1>Add New Person</h1>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="add_new_person">';
        echo '<label for="new_person_name">Name:</label>';
        echo '<input type="text" id="new_person_name" name="new_person_name" required><br>';
        echo '<label for="new_person_email">Email:</label>';
        echo '<input type="email" id="new_person_email" name="new_person_email" required><br>';
        echo '<input type="submit" value="Add New Person">';
        echo '</form>';

        // Check if the form has been submitted
        if (isset($_POST['action']) && $_POST['action'] === 'add_new_person') {
            // Sanitize the input
            $name = sanitize_text_field($_POST['new_person_name']);
            $email = sanitize_email($_POST['new_person_email']);

            global $wpdb;
            $wpdb->insert(
                "{$wpdb->prefix}person",
                array('name' => $name, 'email' => $email),
                array('%s', '%s')
            );

            // Redirect to the main page 
            wp_redirect(admin_url('admin.php?page=contact-management'));
            exit;
        }
    }
    /* --------------//---------------------//---------------------//-----------------------//-------------------------//-------------- */


    /* Edit Person Details page */

    function edit_person_page()
    {
        global $wpdb;

        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;

        // Fetch the person's details
        $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}person WHERE id = %d", $person_id));

        // Display the form
        echo '<h1>Edit Person</h1>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="update_person">';
        echo '<input type="hidden" name="person_id" value="' . $person->id . '">';
        echo '<label for="name">Name:</label>';
        echo '<input type="text" id="name" name="name" value="' . esc_attr($person->name) . '" required><br>';
        echo '<label for="email">Email:</label>';
        echo '<input type="email" id="email" name="email" value="' . esc_attr($person->email) . '" required><br>';
        echo '<input type="submit" value="Update Person">';
        echo '</form>';

        // Check if the form has been submitted
        if (isset($_POST['action']) && $_POST['action'] === 'update_person') {

            $person_id = intval($_POST['person_id']);
            $name = sanitize_text_field($_POST['name']);
            $email = sanitize_email($_POST['email']);

            $wpdb->update(
                "{$wpdb->prefix}person",
                array('name' => $name, 'email' => $email),
                array('id' => $person_id),
                array('%s', '%s'),
                array('%d')
            );

            // Redirect to the main page 
            wp_redirect(admin_url('admin.php?page=contact-management'));
            exit;
        }
    }

    /* ---------------//-----------------------------//----------------------------//--------------------------//------------------------ */

    /* Soft delete */
    function soft_delete_person($person_id)
    {

        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}person",
            array('deleted' => 1),
            array('id' => $person_id),
            array('%d'),
            array('%d')
        );
    }


 /* ---------------//-----------------------------//----------------------------//--------------------------//------------------------ */


    /* Add new contact */

    function new_contact_page()
    {

        //get countries via api
        function get_countries_for_dropdown()
        {
            $countries = [];
            $response = wp_remote_get('https://restcountries.com/v3.1/all');
            if (is_wp_error($response)) {
                return $countries;
            }
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            foreach ($data as $country) {
                $countries[$country['cca2']] = $country['name']['common'] . ' (' . $country['callingCodes'][0] . ')';
            }
            return $countries;
        }


        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;

        global $wpdb;
        $person_name = '';

        if ($person_id > 0) {
            $query = $wpdb->prepare("SELECT name FROM {$wpdb->prefix}person WHERE id = %d", $person_id);
            $person_name = $wpdb->get_var($query);
        }

        // Check if a person ID is provided in the URL
        $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;

        // Fetch countries
        $countries = get_countries_for_dropdown();
        // var_dump($countries);

        // Form for adding a new contact
        echo '<h1>Add New Contact</h1>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="add_new_contact">';
        echo '<input type="hidden" id="person_id" name="person_id" value="' . $person_id . '">';
        echo '<p>Person Name: ' . esc_html($person_name) . '</p>'; // Display the person's name
        echo '<label for="country_code">Country:</label>';
        echo '<select id="country_code" name="country_code" required><option value="">Select a country</option>';
        foreach ($countries as $code => $name) {
            echo '<option value="' . $code . '">' . $name . '</option>';
        }
        echo '</select><br>';
        echo '<label for="number">Number:</label>';
        echo '<input type="text" id="number" name="number" required><br>';
        echo '<input class="center" type="submit" value="Add New Contact">';
        echo '</form>';

        if (isset($_POST['action']) && $_POST['action'] === 'add_new_contact') {

            $person_id = intval($_POST['person_id']);
            $country_code = sanitize_text_field($_POST['country_code']);
            $number = sanitize_text_field($_POST['number']);

            global $wpdb;
            $wpdb->insert(
                "{$wpdb->prefix}contacts",
                array('person_id' => $person_id, 'country_code' => $country_code, 'number' => $number),
                array('%d', '%s', '%s')
            );

            // Redirect to the main page 
            wp_redirect(admin_url('admin.php?page=contact-management'));
            exit;
        }
    }



    /* --------------//-----------------//--------------------//-------------------------//--------------------------//---------- */


    /* Shortcode for listing people  */

    function list_people_shortcode($atts) {
        // Extract shortcode attributes
        $atts = shortcode_atts(
            array(
                'name' => '', // Filter by name
                'email' => '', // Filter by email
                // Add more filters as needed
            ),
            $atts,
            'list_people'
        );
    
        ob_start();
    
        global $wpdb;
    
        $query = "SELECT * FROM {$wpdb->prefix}person WHERE 1=1";
    
        // Apply filters
        if (!empty($atts['name'])) {
            $query .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($atts['name']) . '%');
        }
        if (!empty($atts['email'])) {
            $query .= $wpdb->prepare(" AND email LIKE %s", '%' . $wpdb->esc_like($atts['email']) . '%');
        }
    
        // Execute the query
        $people = $wpdb->get_results($query);
    
        if (!empty($people)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th scope="col">ID</th>';
            echo '<th scope="col">Name</th>';
            echo '<th scope="col">Email</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
    
            foreach ($people as $person) {
                echo '<tr>';
                echo '<td>' . $person->id . '</td>';
                echo '<td>' . $person->name . '</td>';
                echo '<td>' . $person->email . '</td>';
                echo '</tr>';
            }
    
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No people found.</p>';
        }
    
        return ob_get_clean();
    }
    

}

if (class_exists('ContactManagement')) {
    $contactManagement = new ContactManagement();
    
}


register_activation_hook(__FILE__, function () use ($contactManagement) {
    $contactManagement->people_contacts_create_tables();
});
register_deactivation_hook(__FILE__, array($contactManagement, 'deactivate'));
