<?php
if (!defined('ABSPATH')) exit;

function hs_user_dept_field($user) {
    if (!current_user_can('hs_manage_hiring')) return;
    $depts = HS_Job::get_departments();
    $user_dept = get_user_meta($user->ID, 'hs_department', true);
    ?>
    <h3><?php _e('Hiring System', 'hiring-system'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="hs_department"><?php _e('Department', 'hiring-system'); ?></label></th>
            <td>
                <select name="hs_department" id="hs_department">
                    <option value=""><?php _e('— None —', 'hiring-system'); ?></option>
                    <?php foreach ($depts as $dept): ?>
                    <option value="<?php echo esc_attr($dept); ?>" <?php selected($user_dept, $dept); ?>>
                        <?php echo esc_html($dept); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Assign a department to restrict this user to jobs/applications in that department.', 'hiring-system'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

function hs_save_user_dept_field($user_id) {
    if (!current_user_can('hs_manage_hiring')) return;
    if (isset($_POST['hs_department'])) {
        update_user_meta($user_id, 'hs_department', sanitize_text_field($_POST['hs_department']));
    }
}
