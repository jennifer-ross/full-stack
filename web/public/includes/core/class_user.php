<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access, first_name, last_name, plot_id, email  FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_str' => phone_formatting($row['phone']),
                'email' => $row['email'],
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0,
                'plot_id' => 0,
                'first_name' => 0,
                'last_name' => 0,
                'phone_str' => 0,
                'email' => 0,
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    /**
     * Fetches a list of users based on search criteria and pagination.
     *
     * @param array $data An array of search and pagination parameters.
     * @return array An array containing the list of users and pagination details.
     */
    public static function users_list($d = []) {
        // Extract search and pagination parameters
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;

        // Initialize the list of items
        $items = [];

        // Build the WHERE clause for search criteria
        $where = [];
        if ($search) $where[] = "phone LIKE '%" . $search . "%' OR email LIKE '%" . $search . "%' OR first_name LIKE '%" . $search . "%'";
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";

        // Fetch users from the database
        $q = DB::query("SELECT user_id, first_name, last_name, phone, email, plot_id, last_login
        FROM users " . $where . " ORDER BY user_id LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());

        // Process each user record
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone_str' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'last_login' => !empty($row['last_login']) ? date('Y/m/d H:i', $row['last_login']) : 0,
            ];
        }

        // Calculate the total count of users for pagination
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;

        // Build the pagination URL
        $url = 'users?';
        if ($search) $url .= '&search='.$search;

        // Generate the pagination details
        paginator($count, $offset, $limit, $url, $paginator);

        // Return the list of users and pagination details
        return ['items' => $items, 'paginator' => $paginator];
    }


    public static function user_delete_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_delete.html')];
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_delete_update($d = []) {
        //vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        }

        // output
        return User::users_fetch(['offset' => $offset]);
    }

    /**
     * Updates a user's information and returns a list of users.
     *
     * @param array $d The data array containing user information.
     * @return array The list of users.
     */
    public static function user_edit_update($d = []) {
        // Get the plot IDs from the data array
        $plots = isset($d['plot_id']) && trim($d['plot_id']) ? trim($d['plot_id']) : 0;
        $existing_plots = [];

        // Check if there are plots to process
        if (!empty($plots)) {
            // Split the plot IDs into an array
            $plots = explode(',', $plots);

            // Iterate over each plot ID
            foreach ($plots as $plot_key => $plot_id) {
                // Check if the plot ID is numeric
                if (is_numeric($plot_id)) {
                    // Get the plot information
                    $plot = Plot::plot_info($plot_id);

                    // Check if the plot exists
                    if ($plot && !empty($plot['id'])) {
                        // Add the existing plot_id to the list
                        $existing_plots[] = $plot_id;
                    }
                }
            }
        }

        // Convert the existing plots array to a comma-separated string
        $plots = implode(',', $existing_plots);

        // Create an array of required fields
        $required_fields = [
            'first_name' => isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : 0,
            'last_name' => isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : 0,
            'email' => isset($d['email']) && trim($d['email']) ? trim(strtolower($d['email'])) : 0,
            'phone' => isset($d['phone'])? preg_replace('~\D+~', '', $d['phone'])  : 0,
        ];

        // Initialize variables
        $can_update = true;
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        // Check if all required fields are not empty
        foreach ($required_fields as $field) {
            if (empty($field)) {
                $can_update = false;
                break;
            }
        }

        // Check if the user can be updated
        if ($can_update) {
            // Update the user information
            if ($user_id) {
                $set = [];
                $set[] = "first_name='".$required_fields['first_name']."'";
                $set[] = "last_name='".$required_fields['last_name']."'";
                $set[] = "email='".$required_fields['email']."'";
                $set[] = "phone='".$required_fields['phone']."'";
                $set[] = "plot_id='".$plots."'";
                $set[] = "updated='".Session::$ts."'";
                $set = implode(", ", $set);
                DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO users (
                first_name,
                last_name,
                email,
                phone,
                plot_id,
                updated
            ) VALUES (
                '".$required_fields['first_name']."',
                '".$required_fields['last_name']."',
                '".$required_fields['email']."',
                ".$required_fields['phone'].",
                '".$plots."',
                '".Session::$ts."'
            );") or die (DB::error());
            }
        }

        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }
}
