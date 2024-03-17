<?php
/*
Plugin Name: IT Help Desk
Description: Plugin to manage IT help desk work history.
Version: 1.0
Author: Hiroshi Ozeki
*/

register_activation_hook(__FILE__, 'help_desk_activate');
register_deactivation_hook(__FILE__, 'help_desk_deactivate');


// Enqueue the style
function enqueue_your_plugin_style()
{
    wp_enqueue_style('help-desk-style', plugins_url('/assets/css/style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'enqueue_your_plugin_style');


function enqueue_chart_script()
{
    // Get the plugin directory path
    $plugin_dir = plugin_dir_url(__FILE__);

    // Load Chart.js first
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js');

    // Then load the custom script
    // wp_enqueue_script('chart-script', $plugin_dir . 'assets/js/chart-script.js', array('chart-js'), null, true);
}
add_action('admin_enqueue_scripts', 'enqueue_chart_script');


// Process when the plugin is activated
function help_desk_activate()
{
    global $wpdb;

    // Execute SQL to create tables
    $charset_collate = $wpdb->get_charset_collate();

    $sql_staff = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}helpdesk_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) $charset_collate;";

    $sql_location = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}helpdesk_location (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) $charset_collate;";

    $sql_type = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}helpdesk_type (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL
    ) $charset_collate;";

    $sql_requesting_staff = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}helpdesk_requesting_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT,
    requesting_staff_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (location_id) REFERENCES {$wpdb->prefix}helpdesk_location(id)
    ) $charset_collate;";


    $sql_history = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}helpdesk_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME,
    staff_id INT,
    location_id INT,
    requesting_staff_id INT,
    type_id INT,
    issue_details TEXT,
    response_details TEXT,
    FOREIGN KEY (staff_id) REFERENCES {$wpdb->prefix}helpdesk_staff(id),
    FOREIGN KEY (location_id) REFERENCES {$wpdb->prefix}helpdesk_location(id),
    FOREIGN KEY (type_id) REFERENCES {$wpdb->prefix}helpdesk_type(id),
    FOREIGN KEY (requesting_staff_id) REFERENCES {$wpdb->prefix}helpdesk_requesting_staff(id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_staff);
    dbDelta($sql_location);
    dbDelta($sql_requesting_staff);
    dbDelta($sql_type);
    dbDelta($sql_history);
}

// Process when the plugin is deactivated
function help_desk_deactivate()
{
    global $wpdb;

    // Execute SQL to drop tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}helpdesk_staff");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}helpdesk_location");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}helpdesk_type");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}helpdesk_history");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}helpdesk_requesting_staff");
}

// Plugin initialization
add_action('plugins_loaded', 'help_desk_init');

// Add admin menu
add_action('admin_menu', 'help_desk_add_menu');

function help_desk_init()
{
    // Add any initialization process here
}

function help_desk_add_menu()
{
    add_menu_page('helpdesk-dashboard', 'HelpDesk', 'manage_options', 'helpdesk-menu', 'help_desk_dashboard');
    add_submenu_page('helpdesk-menu', 'Work Content', 'Work Content', 'manage_options', 'work-content', 'help_desk_work_content');
    add_submenu_page('helpdesk-menu', 'Work Staff', 'Work Staff', 'manage_options', 'work-staff', 'help_desk_work_staff');
    add_submenu_page('helpdesk-menu', 'Work Location', 'Work Location', 'manage_options', 'work-location', 'help_desk_work_location');
    add_submenu_page('helpdesk-menu', 'Work Category', 'Work Category', 'manage_options', 'work-category', 'help_desk_work_category');
    
    // Add submenu page for managing requesting staff
    add_submenu_page('helpdesk-menu', 'Requesting Staff', 'Requesting Staff', 'manage_options', 'requesting-staff', 'help_desk_requesting_staff');
}


// Dashboard
function help_desk_dashboard()
{
    // Add dashboard code
    global $wpdb;

    // Get data for overall work categories ratio
    $categories_data = $wpdb->get_results("SELECT type_id, COUNT(*) as count FROM {$wpdb->prefix}helpdesk_history GROUP BY type_id");

    // Get data for overall work locations ratio
    $locations_data = $wpdb->get_results("SELECT location_id, COUNT(*) as count FROM {$wpdb->prefix}helpdesk_history GROUP BY location_id");

    // Get data for monthly work content count
    $monthly_data = $wpdb->get_results("SELECT DATE_FORMAT(date_created, '%Y-%m') as month, COUNT(*) as count FROM {$wpdb->prefix}helpdesk_history GROUP BY month");

    // Extract data for chart.js
    $categories_labels = [];
    $categories_values = [];
    foreach ($categories_data as $category) {
        $category_name = get_category_name($category->type_id);
        $categories_labels[] = $category_name;
        $categories_values[] = $category->count;
    }

    $locations_labels = [];
    $locations_values = [];
    foreach ($locations_data as $location) {
        $location_name = get_location_name($location->location_id); // Assuming a function get_location_name is defined to get location name
        $locations_labels[] = $location_name;
        $locations_values[] = $location->count;
    }

    $monthly_labels = [];
    $monthly_values = [];
    foreach ($monthly_data as $month) {
        $monthly_labels[] = $month->month;
        $monthly_values[] = $month->count;
    }

    // Output the HTML and JavaScript for the dashboard
?>
    <div class="wrap">
        <h2>Statistics</h2>

        <div class='canvas-container'>
            <!-- Overall work categories ratio chart -->
            <canvas id="categories-chart" width="300" height="150"></canvas>
        </div>

        <div class='canvas-container'>
            <!-- Overall work locations ratio chart -->
            <canvas id="locations-chart" width="300" height="150"></canvas>
        </div>

        <div class='canvas-container'>
            <!-- Monthly work content count chart -->
            <canvas id="monthly-chart" width="300" height="150"></canvas>
        </div>

        <script>
            // Chart.js initialization
            var categoriesData = {
                labels: <?php echo json_encode($categories_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($categories_values); ?>,
                    backgroundColor: ['red', 'blue', 'green', 'yellow', 'orange'],
                }]
            };

            var locationsData = {
                labels: <?php echo json_encode($locations_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($locations_values); ?>,
                    backgroundColor: ['red', 'blue', 'green', 'yellow', 'orange'],
                }]
            };

            var monthlyData = {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Monthly Work Content Count',
                    data: <?php echo json_encode($monthly_values); ?>,
                    borderColor: 'blue',
                    borderWidth: 2,
                    fill: false,
                }]
            };

            var categoriesChart = new Chart(document.getElementById('categories-chart'), {
                type: 'pie',
                data: categoriesData,
            });

            var locationsChart = new Chart(document.getElementById('locations-chart'), {
                type: 'pie',
                data: locationsData,
            });

            var monthlyChart = new Chart(document.getElementById('monthly-chart'), {
                type: 'line',
                data: monthlyData,
                options: {
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'month',
                            },
                        },
                        y: {
                            beginAtZero: true,
                        },
                    },
                },
            });
        </script>
    </div>
<?php
}


// Work Content Page
function help_desk_work_content()
{
    // Add code for the Work Content page
    // Function for handling work content
    global $wpdb;

    // Retrieve reference data
    $staff_members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_staff");
    $locations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_location");
    $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_type");
    $requesting_staff_members= $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_requesting_staff");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_work_content'])) {
            // Get data submitted from the form
            $staff_id = absint($_POST['staff_id']);
            $location_id = absint($_POST['location_id']);
            $requesting_staff_id = absint($_POST['requesting_staff_id']);
            $category_id = absint($_POST['category_id']);
            $issue_details = sanitize_text_field($_POST['issue_details']);
            $response_details = sanitize_text_field($_POST['response_details']);
            $timestamp = isset($_POST['timestamp']) ? sanitize_text_field($_POST['timestamp']) : current_time('mysql', 1);

            // Register data in the database
            $wpdb->insert(
                $wpdb->prefix . 'helpdesk_history',
                array(
                    'staff_id' => $staff_id,
                    `requesting_staff_id` => $requesting_staff_id,
                    'location_id' => $location_id,
                    'type_id' => $category_id,
                    'issue_details' => $issue_details,
                    'response_details' => $response_details,
                    'timestamp' => $timestamp,
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s')
            );
        } elseif (isset($_POST['delete_work_content'])) {
            // Process when the delete button is clicked
            $work_content_id = absint($_POST['delete_work_content']);

            echo '<script>';
            echo 'var confirmation = confirm("Are you sure you want to delete this?");';
            echo 'if (!confirmation) {';
            echo '  event.preventDefault();';  // 削除をキャンセル
            echo '}';
            echo '</script>';

            $wpdb->delete(
                $wpdb->prefix . 'helpdesk_history',
                array('id' => $work_content_id),
                array('%d')
            );
        } elseif (isset($_POST['edit_work_content'])) {
            // Process when the edit button is clicked
            $work_content_id = absint($_POST['edit_work_content']);
            $work_content = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}helpdesk_history WHERE id = {$work_content_id}");

            // Display the edit form
            echo '<div class="work-content-edit-form">';
            echo '<h3>Edit Work Content</h3>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="edit_work_content_id" value="' . $work_content_id . '">';

            // Add table tag
            echo '<table>';

            // Dropdown menu for staff members
            echo '<tr><td><label for="staff_id">Staff:</label></td>';
            echo '<td><select name="staff_id" required>';
            foreach ($staff_members as $staff) {
                $selected = ($staff->id === $work_content->staff_id) ? 'selected' : '';
                echo '<option value="' . $staff->id . '" ' . $selected . '>' . esc_html($staff->name) . '</option>';
            }
            echo '</select></td></tr>';

            // Dropdown menu for work locations
            echo '<tr><td><label for="location_id">Location:</label></td>';
            echo '<td><select name="location_id" required>';
            foreach ($locations as $location) {
                $selected = ($location->id === $work_content->location_id) ? 'selected' : '';
                echo '<option value="' . $location->id . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
            }
            echo '</select></td></tr>';

            // Dropdown menu for requesting staff
            echo '<tr><td><label for="requesting_staff_id">Request by:</label></td>';
            echo '<td><select name="requesting_staff_id" required>';
            foreach ($requesting_staff_members as $requesting_staff) {
                $selected = ($requesting_staff->id === $work_content->requesting_staff_id) ? 'selected' : '';
                echo '<option value="' . $requesting_staff->id . '" ' . $selected . '>' . esc_html($requesting_staff->requesting_staff_name) . '</option>';
            }
            echo '</select></td></tr>';

            // Dropdown menu for work categories
            echo '<tr><td><label for="category_id">Category:</label></td>';
            echo '<td><select name="category_id" required>';
            foreach ($categories as $category) {
                $selected = ($category->id === $work_content->type_id) ? 'selected' : '';
                echo '<option value="' . $category->id . '" ' . $selected . '>' . esc_html($category->category_name) . '</option>';
            }
            echo '</select></td></tr>';

            echo '<tr><td><label for="issue_details">Request Details:</label></td>';
            echo '<td><textarea name="issue_details" required>' . esc_textarea($work_content->issue_details) . '</textarea></td></tr>';

            echo '<tr><td><label for="response_details">Response Details:</label></td>';
            echo '<td><textarea name="response_details" required>' . esc_textarea($work_content->response_details) . '</textarea></td></tr>';


            // Input text box for timestamp
            echo '<tr><td><label for="timestamp">Timestamp:</label></td>';
            echo '<td><input type="text" name="timestamp" value="' . esc_attr($work_content->timestamp) . '"></td></tr>';

            echo '</table>';

            echo '<input type="submit" name="confirm_edit_work_content" class="button button-primary" value="Edit">';
            echo '</form>';
            echo '</div>';
        } elseif (isset($_POST['confirm_edit_work_content'])) {
            // Process when the edit is confirmed
            $work_content_id = absint($_POST['edit_work_content_id']);
            $staff_id = absint($_POST['staff_id']);
            $location_id = absint($_POST['location_id']);
            $category_id = absint($_POST['category_id']);
            $requesting_staff_id = absint($_POST['requesting_staff_id']);
            $issue_details = sanitize_text_field($_POST['issue_details']);
            $response_details = sanitize_text_field($_POST['response_details']);
            $timestamp = isset($_POST['timestamp']) ? sanitize_text_field($_POST['timestamp']) : current_time('mysql', 1);

            $wpdb->update(
                $wpdb->prefix . 'helpdesk_history',
                array(
                    'staff_id' => $staff_id,
                    'location_id' => $location_id,
                    `requesting_staff_id` => $requesting_staff_id,
                    'type_id' => $category_id,
                    'issue_details' => $issue_details,
                    'response_details' => $response_details,
                    'timestamp' => $timestamp,
                ),
                array('id' => $work_content_id),
                array('%d', '%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        }
    }

    // Display form and table for work content
    echo '<div class="work-content-form">';
    echo '<h3>Register Work Content</h3>';
    echo '<form method="post" action="">';

    // Add table tag
    echo '<table>';

    // Dropdown menu for staff members
    echo '<tr><td><label for="staff_id">Staff:</label></td>';
    echo '<td><select name="staff_id" required>';
    foreach ($staff_members as $staff) {
        echo '<option value="' . $staff->id . '">' . esc_html($staff->name) . '</option>';
    }
    echo '</select></td></tr>';

    // Dropdown menu for work locations
    echo '<tr><td><label for="location_id">Location:</label></td>';
    echo '<td><select name="location_id" required>';
    foreach ($locations as $location) {
        echo '<option value="' . $location->id . '">' . esc_html($location->name) . '</option>';
    }
    echo '</select></td></tr>';

    // Dropdown menu for requesting_staff
    echo '<tr><td><label for="requesting_staff_id">Request BY:</label></td>';
    echo '<td><select name="requesting_staff_id" required>';
    foreach ($requesting_staff_members as $requesting_staff) {
        echo '<option value="' . $requesting_staff->id . '">' . esc_html($requesting_staff->requesting_staff_name) . '</option>';
    }
    echo '</select></td></tr>';

    // Dropdown menu for work categories
    echo '<tr><td><label for="category_id">Category:</label></td>';
    echo '<td><select name="category_id" required>';
    foreach ($categories as $category) {
        echo '<option value="' . $category->id . '">' . esc_html($category->category_name) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><td><label for="issue_details">Request Details:</label></td>';
    echo '<td><textarea name="issue_details" required></textarea></td></tr>';

    echo '<tr><td><label for="response_details">Response Details:</label></td>';
    echo '<td><textarea name="response_details" required></textarea></td></tr>';

    // Input text box for timestamp
    echo '<tr><td><label for="timestamp">Timestamp:</label></td>';
    echo '<td><input type="text" name="timestamp" value="' . esc_attr(current_time('mysql', 1)) . '"></td></tr>';

    echo '</table>';

    echo '<input type="submit" name="add_work_content" class="button button-primary" value="Register">';
    echo '</form>';
    echo '</div>';

    // Function to display work content list
    function display_work_content_list()
    {
        global $wpdb;

        // Retrieve reference data
        $staff_members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_staff");
        $locations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_location");
        $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_type");
        // Get data for requesting staff
        $requesting_staff_members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_requesting_staff");

        // Display search form
        echo '<div class="work-content-search">';
        echo '<h3>Search Work Content</h3>';
        echo '<form method="post" action="">';

        // Search by Staff
        echo '<label for="staff_search">Staff:</label>';
        echo '<select name="staff_search">';
        echo '<option value="">All</option>';
        foreach ($staff_members as $staff) {
            $selected = (isset($_POST['staff_search']) && $_POST['staff_search'] == $staff->id) ? 'selected' : '';
            echo '<option value="' . $staff->id . '" ' . $selected . '>' . esc_html($staff->name) . '</option>';
        }
        echo '</select>';

        // Search by Location
        echo '<label for="location_search">Location:</label>';
        echo '<select name="location_search">';
        echo '<option value="">All</option>';
        foreach ($locations as $location) {
            $selected = (isset($_POST['location_search']) && $_POST['location_search'] == $location->id) ? 'selected' : '';
            echo '<option value="' . $location->id . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
        }
        echo '</select>';


        // Search by requesting_Staff
        echo '<label for="requesting_staff_search">Requester:</label>';
        echo '<select name="requesting_staff_search">';
        echo '<option value="">All</option>';
        foreach ($requesting_staff_members as $requesting_staff_member) {
            $selected = (isset($_POST['requesting_staff_search']) && $_POST['requesting_staff_search'] == $requesting_staff->id) ? 'selected' : '';
            echo '<option value="' . $requesting_staff_member->id . '" ' . $selected . '>' . esc_html($requesting_staff_member->name) . '</option>';
        }
        echo '</select>';

        // Search by Category
        echo '<label for="category_search">Category:</label>';
        echo '<select name="category_search">';
        echo '<option value="">All</option>';
        foreach ($categories as $category) {
            $selected = (isset($_POST['category_search']) && $_POST['category_search'] == $category->id) ? 'selected' : '';
            echo '<option value="' . $category->id . '" ' . $selected . '>' . esc_html($category->category_name) . '</option>';
        }
        echo '</select>';


        // Keyword Search
        echo '<label for="keyword_search">Keyword:</label>';
        echo '<input type="text" name="keyword_search" value="' . esc_attr(isset($_POST['keyword_search']) ? $_POST['keyword_search'] : '') . '">';

        echo '<input type="submit" class="button button-primary" value="Search">';
        echo '</form>';
        echo '</div>';
        // ...

        // Modify the SQL query based on the search parameters
        $sql = "SELECT * FROM {$wpdb->prefix}helpdesk_history WHERE 1=1";

        if (isset($_POST['staff_search']) && !empty($_POST['staff_search'])) {
            $sql .= $wpdb->prepare(" AND staff_id = %d", $_POST['staff_search']);
        }

        if (isset($_POST['location_search']) && !empty($_POSt['location_search'])) {
            $sql .= $wpdb->prepare(" AND location_id = %d", $_POST['location_search']);
        }

        if (isset($_POST['requesting_staff_search']) && !empty($_POSt['requesting_staff_search'])) {
            $sql .= $wpdb->prepare(" AND requesting_staff_id = %d", $_POST['requesting_staff_search']);
        }

        if (isset($_POST['category_search']) && !empty($_POST['category_search'])) {
            $sql .= $wpdb->prepare(" AND type_id = %d", $_POST['category_search']);
        }

        if (isset($_POST['requesting_staff_search']) && !empty($_POST['requesting_staff_search'])) {
            $sql .= $wpdb->prepare(" AND staff_id = %d", $_POST['requesting_staff_search']);
        }

        if (isset($_POST['keyword_search']) && !empty($_POST['keyword_search'])) {
            $keyword = sanitize_text_field($_POST['keyword_search']);
            $sql .= $wpdb->prepare(" AND (issue_details LIKE %s OR response_details LIKE %s)", "%$keyword%", "%$keyword%");
        }


        // Display the retrieved data

        $records_per_page = 10; // Number of records to display per page
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1; // Get the current page or set it to 1

        // Calculate the offset to retrieve the correct set of records
        $offset = ($current_page - 1) * $records_per_page;

        $contents = $wpdb->get_results(
            $wpdb->prepare($sql .  " ORDER BY id desc  LIMIT %d OFFSET %d", $records_per_page, $offset)
            //$wpdb->prepare("SELECT * FROM {$wpdb->prefix}helpdesk_history LIMIT %d OFFSET %d", $records_per_page, $offset)
        );


        echo '<div class="work-content-list">';
        echo '<h3>Work Content List</h3>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Staff</th>';
        echo '<th>Location</th>';
        echo '<th>Requested By</th>';
        echo '<th>Category</th>';
        echo '<th>Issue Details</th>';
        echo '<th>Response Details</th>';
        echo '<th>Timestamp</th>';
        echo '<th></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($contents as $content) {
            echo '<tr>';
            echo '<td>' . $content->id . '</td>';
            echo '<td>' . esc_html(get_staff_member_name($content->staff_id)) . '</td>';
            echo '<td>' . esc_html(get_location_name($content->location_id)) . '</td>';
            echo '<td>' . esc_html(get_requesting_staff_member_name($content->requesting_staff_id)) . '</td>'; 
            echo '<td>' . esc_html(get_category_name($content->type_id)) . '</td>';
            echo '<td>' . custom_wrap_text(esc_html($content->issue_details), 80) . '</td>';
            echo '<td>' . custom_wrap_text(esc_html($content->response_details), 80) . '</td>';
            echo '<td>' . esc_html($content->timestamp) . '</td>';
            echo '<td>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="edit_work_content" value="' . $content->id . '">';
            echo '<input type="submit" value="Edit">';
            echo '</form>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="delete_work_content" value="' . $content->id . '">';
            echo '<input type="submit" value="Delete">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Display pagination links
        $total_records = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}helpdesk_history");
        $total_pages = ceil($total_records / $records_per_page);

        echo '<div class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = ($current_page == $i) ? 'current' : '';
            echo "<a class='$class' href='?page=work-content&paged=$i'>$i</a>";
        }
        echo '</div>';

        echo '</div>';
    }

    // Custom function to wrap text within specified width and handle existing line breaks
    function custom_wrap_text($text, $width)
    {
        // Handle existing line breaks
        $text = nl2br($text);
        // Wrap text to the specified width without breaking words
        $wrapped_text = wordwrap($text, $width, "<br>", false);
        return $wrapped_text;
    }

    // Display work content list
    display_work_content_list();
}



// Add or modify as needed in your-plugin-name.php

// Function to get staff member name by ID
function get_staff_member_name($staff_id)
{
    global $wpdb;
    $staff_member = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}helpdesk_staff WHERE id = %d", $staff_id));
    return $staff_member ? $staff_member->name : '';
}

function get_requesting_staff_member_name($requsting_staff_id)
{
    global $wpdb;
    $requesting_staff_member = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}helpdesk_requesting_staff WHERE id = %d", $requesting_staff_id));
    return $requesting_staff_member ? $requesting_staff_member->name : '';
}

// Function to get location name by ID
function get_location_name($location_id)
{
    global $wpdb;
    $location = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}helpdesk_location WHERE id = %d", $location_id));
    return $location ? $location->name : '';
}

// Function to get category name by ID
function get_category_name($category_id)
{
    global $wpdb;
    $category = $wpdb->get_row($wpdb->prepare("SELECT category_name FROM {$wpdb->prefix}helpdesk_type WHERE id = %d", $category_id));
    return $category ? $category->category_name : '';
}

// Work Staff Page
function help_desk_work_staff()
{
    global $wpdb;

    // Process when a new staff member is added
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_staff'])) {
            // Get data submitted from the form and process adding to the database
            $staff_name = sanitize_text_field($_POST['staff_name']);
            $wpdb->insert(
                $wpdb->prefix . 'helpdesk_staff',
                array('name' => $staff_name),
                array('%s')
            );
        } elseif (isset($_POST['delete_staff'])) {
            // Process when the delete button is clicked
            $staff_id = absint($_POST['delete_staff_id']);
            echo '<script>';
            echo 'var confirmation = confirm("Are you sure you want to delete this?");';
            echo 'if (!confirmation) {';
            echo '  event.preventDefault();';  // 削除をキャンセル
            echo '}';
            echo '</script>';

            $wpdb->delete(
                $wpdb->prefix . 'helpdesk_staff',
                array('id' => $staff_id),
                array('%d')
            );
        } elseif (isset($_POST['edit_staff'])) {
            // Process when the edit button is clicked
            $staff_id = absint($_POST['edit_staff_id']);
            $staff = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}helpdesk_staff WHERE id = {$staff_id}");

            // Display the edit form
            echo '<h3>Edit Staff Member</h3>';
            echo '<form method="post" action="">';
            echo '<input type="text" name="edit_staff_id" value="' . $staff_id . '">';
            echo '<label for="new_staff_name">New Staff Member Name:</label>';
            echo '<input type="text" name="new_staff_name" value="' . esc_attr($staff->name) . '" required>';
            echo '<input type="submit" name="confirm_edit_staff" value="Edit">';
            echo '</form>';
        } elseif (isset($_POST['confirm_edit_staff'])) {
            // Process when the edit is confirmed
            $staff_id = absint($_POST['edit_staff_id']);
            $new_staff_name = sanitize_text_field($_POST['new_staff_name']);
            $wpdb->update(
                $wpdb->prefix . 'helpdesk_staff',
                array('name' => $new_staff_name),
                array('id' => $staff_id),
                array('%s'),
                array('%d')
            );
        }
    }
?>
    <div class="wrap">
        <h2>Work Staff Members</h2>

        <!-- Add new staff member form -->
        <form method="post" action="">
            <label for="staff_name">Staff Member Name:</label>
            <input type="text" name="staff_name" required>
            <input type="submit" name="add_staff" value="Add">
        </form>

        <!-- Display list of work staff members -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Staff Member Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Retrieve the list of work staff members from the database
                $staff_members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_staff");

                foreach ($staff_members as $staff) {
                    echo "<tr>";
                    echo "<td>{$staff->id}</td>";
                    echo "<td>{$staff->name}</td>";
                    echo "<td>";
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="delete_staff_id" value="' . $staff->id . '">';
                    echo '<input type="submit" name="delete_staff" value="Delete">';
                    echo '</form>';
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="edit_staff_id" value="' . $staff->id . '">';
                    echo '<input type="submit" name="edit_staff" value="Edit">';
                    echo '</form>';
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}


// Work Location Page
function help_desk_work_location()
{
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_location'])) {
            $location_name = sanitize_text_field($_POST['location_name']);
            $wpdb->insert(
                $wpdb->prefix . 'helpdesk_location',
                array('name' => $location_name),
                array('%s')
            );
        } elseif (isset($_POST['delete_location'])) {
            $location_id = absint($_POST['delete_location_id']);
            echo '<script>';
            echo 'var confirmation = confirm("Are you sure you want to delete this?");';
            echo 'if (!confirmation) {';
            echo '  event.preventDefault();';  // 削除をキャンセル
            echo '}';
            echo '</script>';

            $wpdb->delete(
                $wpdb->prefix . 'helpdesk_location',
                array('id' => $location_id),
                array('%d')
            );
        } elseif (isset($_POST['edit_location'])) {
            $location_id = absint($_POST['edit_location_id']);
            $location = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}helpdesk_location WHERE id = {$location_id}");

            echo '<h3>Edit Work Location</h3>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="edit_location_id" value="' . $location_id . '">';
            echo '<label for="new_location_name">New Work Location Name:</label>';
            echo '<input type="text" name="new_location_name" value="' . esc_attr($location->name) . '" required>';
            echo '<input type="submit" name="confirm_edit_location" value="Edit">';
            echo '</form>';
        } elseif (isset($_POST['confirm_edit_location'])) {
            $location_id = absint($_POST['edit_location_id']);
            $new_location_name = sanitize_text_field($_POST['new_location_name']);
            $wpdb->update(
                $wpdb->prefix . 'helpdesk_location',
                array('name' => $new_location_name),
                array('id' => $location_id),
                array('%s'),
                array('%d')
            );
        }
    }
?>
    <div class="wrap">
        <h2>Work Locations</h2>

        <!-- Add new work location form -->
        <form method="post" action="">
            <label for="location_name">Work Location:</label>
            <input type="text" name="location_name" required>
            <input type="submit" name="add_location" value="Add">
        </form>

        <!-- Display list of work locations -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Work Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Retrieve the list of work locations from the database
                $locations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_location");

                // Display the retrieved data
                foreach ($locations as $location) {
                    echo "<tr>";
                    echo "<td>{$location->id}</td>";
                    echo "<td>{$location->name}</td>";
                    echo "<td>";
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="delete_location_id" value="' . $location->id . '">';
                    echo '<input type="submit" name="delete_location" value="Delete">';
                    echo '</form>';
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="edit_location_id" value="' . $location->id . '">';
                    echo '<input type="submit" name="edit_location" value="Edit">';
                    echo '</form>';
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}


// Work Category Page
function help_desk_work_category()
{
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_category'])) {
            $category_name = sanitize_text_field($_POST['category_name']);
            $wpdb->insert(
                $wpdb->prefix . 'helpdesk_type',
                array('category_name' => $category_name),
                array('%s')
            );
        } elseif (isset($_POST['delete_category'])) {
            $category_id = absint($_POST['delete_category_id']);
            echo '<script>';
            echo 'var confirmation = confirm("Are you sure you want to delete this?");';
            echo 'if (!confirmation) {';
            echo '  event.preventDefault();';  // 削除をキャンセル
            echo '}';
            echo '</script>';

            $wpdb->delete(
                $wpdb->prefix . 'helpdesk_type',
                array('id' => $category_id),
                array('%d')
            );
        } elseif (isset($_POST['edit_category'])) {
            $category_id = absint($_POST['edit_category_id']);
            $category = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}helpdesk_type WHERE id = {$category_id}");

            echo '<h3>Edit Work Category</h3>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="edit_category_id" value="' . $category_id . '">';
            echo '<label for="new_category_name">New Work Category Name:</label>';
            echo '<input type="text" name="new_category_name" value="' . esc_attr($category->category_name) . '" required>';
            echo '<input type="submit" name="confirm_edit_category" value="Edit">';
            echo '</form>';
        } elseif (isset($_POST['confirm_edit_category'])) {
            $category_id = absint($_POST['edit_category_id']);
            $new_category_name = sanitize_text_field($_POST['new_category_name']);
            $wpdb->update(
                $wpdb->prefix . 'helpdesk_type',
                array('category_name' => $new_category_name),
                array('id' => $category_id),
                array('%s'),
                array('%d')
            );
        }
    }
?>
    <div class="wrap">
        <h2>Work Categories</h2>

        <!-- Add new work category form -->
        <form method="post" action="">
            <label for="category_name">Work Category:</label>
            <input type="text" name="category_name" required>
            <input type="submit" name="add_category" value="Add">
        </form>

        <!-- Display list of work categories -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Work Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Retrieve the list of work categories from the database
                $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_type");

                // Display the retrieved data
                foreach ($categories as $category) {
                    echo "<tr>";
                    echo "<td>{$category->id}</td>";
                    echo "<td>{$category->category_name}</td>";
                    echo "<td>";
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="delete_category_id" value="' . $category->id . '">';
                    echo '<input type="submit" name="delete_category" value="Delete">';
                    echo '</form>';
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="edit_category_id" value="' . $category->id . '">';
                    echo '<input type="submit" name="edit_category" value="Edit">';
                    echo '</form>';
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}
function help_desk_requesting_staff()
{
    global $wpdb;

    // Process when a new requesting staff member is added
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_requesting_staff'])) {
            // Get data submitted from the form and process adding to the database
            $requesting_staff_name = sanitize_text_field($_POST['requesting_staff_name']);
            $location_id = absint($_POST['location_id']); // Assuming location is selected from dropdown
            $wpdb->insert(
                $wpdb->prefix . 'helpdesk_requesting_staff',
                array(
                    'location_id' => $location_id,
                    'requesting_staff_name' => $requesting_staff_name
                ),
                array('%d', '%s')
            );
        } elseif (isset($_POST['delete_requesting_staff'])) {
            // Process when the delete button is clicked
            $requesting_staff_id = absint($_POST['delete_requesting_staff_id']);
            echo '<script>';
            echo 'var confirmation = confirm("Are you sure you want to delete this?");';
            echo 'if (!confirmation) {';
            echo '  event.preventDefault();';  // 削除をキャンセル
            echo '}';
            echo '</script>';

            $wpdb->delete(
                $wpdb->prefix . 'helpdesk_requesting_staff',
                array('id' => $requesting_staff_id),
                array('%d')
            );
            // Process when the delete button is clicked
            // Process deletion
        } elseif (isset($_POST['edit_requesting_staff'])) {
            // Process when the edit button is clicked
            $requesting_staff_id = absint($_POST['edit_requesting_staff_id']);
            $requesting_staff = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}helpdesk_requesting_staff WHERE id = {$requesting_staff_id}");
            $locations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_location GROUP BY id");

            // Display the edit form
            echo '<h3>Edit Staff Member</h3>';
            echo '<form method="post" action="">';
            echo '<input type="text" name="edit_requesting_staff_id" value="' . $requesting_staff_id . '">';
             // Dropdown menu for staff members
            echo '<label for="location_id">Location:</label>';
            echo '<select name="new_location_id" required>';
            foreach ($locations as $location) {
                 $selected = ($location->id === $requesting_staff->location_id) ? 'selected' : '';
                 echo '<option value="' . $location->id . '" ' . $selected . '>' . esc_html($location->name) . '</option>';
            }
            echo '</select>';
            echo '<label for="new_requesting_staff_name">New Requester Name:</label>';
            echo '<input type="text" name="new_requesting_staff_name" value="' . esc_attr($requesting_staff->requesting_staff_name) . '" required>';
            echo '<input type="submit" name="confirm_edit_requesting_staff" value="Edit">';
            echo '</form>';
        } elseif (isset($_POST['confirm_edit_requesting_staff'])) {
            $requesting_staff_id = absint($_POST['edit_requesting_staff_id']);
            $new_requesting_staff_name = sanitize_text_field($_POST['new_requesting_staff_name']);
            $new_location_id = sanitize_text_field($_POST['new_location_id']);
            $wpdb->update(
                $wpdb->prefix . 'helpdesk_requesting_staff',
                array('location_id' => $new_location_id, 'requesting_staff_name' => $new_requesting_staff_name),
                array('id' => $requesting_staff_id),
                array('%d','%s'),
                array('%d')
            );
        }
    }
?>
    <div class="wrap">
        <h2>Requesting Staff Members</h2>

        <!-- Add new requesting staff member form -->
        <form method="post" action="">
            <label for="requesting_staff_name">Requesting Staff Member Name:</label>
            <input type="text" name="requesting_staff_name" required>
            <label for="location_id">Location:</label>
            <select name="location_id">
                <?php
                // Retrieve the list of locations from the database
                $locations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_location");
                foreach ($locations as $location) {
                    echo '<option value="' . $location->id . '">' . $location->name . '</option>';
                }
                ?>
            </select>
            <input type="submit" name="add_requesting_staff" value="Add">
        </form>
<!-- Display list of work categories -->
<table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Requester</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Retrieve the list of work categories from the database
                $requesting_staff_members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}helpdesk_requesting_staff");

                // Display the retrieved data
                foreach ($requesting_staff_members as $requesting_staff_member) {
                    $location_name = get_location_name($requesting_staff_member->location_id); // Assuming a function get_location_name is defined to get location name
                    echo "<tr>";
                    echo "<td>{$requesting_staff_member->id}</td>";
                    echo "<td>{$requesting_staff_member->requesting_staff_name}</td>";
                    echo "<td>{$location_name}</td>";
                    echo "<td>";
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="delete_requesting_staff_id" value="' . $requesting_staff_member->id . '">';
                    echo '<input type="submit" name="delete_requesting_staff" value="Delete">';
                    echo '</form>';
                    echo '<form method="post" action="">';
                    echo '<input type="hidden" name="edit_requesting_staff_id" value="' . $requesting_staff_member->id . '">';
                    echo '<input type="submit" name="edit_requesting_staff" value="Edit">';
                    echo '</form>';
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}
