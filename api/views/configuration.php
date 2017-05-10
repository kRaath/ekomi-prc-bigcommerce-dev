<div class="wrap" >
    <h2>Product Review Container Configuration</h2>
    <!-- Displaying Notices if any -->
    <?php if ($response['isSubmited'] == 1): ?>
        <?php if ($response['status'] == 'success'): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($response['message']) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($response['status'] == 'error'): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($response['message']) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST">
        <table name="prc_configuration" class="ekomi-configuration">
            <tr valign="top">
                <th scope="row">
                    <label for="prc_active" ><?php echo __('Status', 'ekomi-prc') ?></label>
                </th>
                <td>
                    <select name="prc_active" id="prc_active" value="<?php echo $data['prc_active'] ?>" >
                        <option value="0" <?php echo ($data['prc_active'] == 0) ? "selected=''" : "" ?> ><?php echo __('Disable', 'ekomi-prc') ?></option>
                        <option value="1" <?php echo ($data['prc_active'] == 1) ? "selected=''" : "" ?> ><?php echo __('Enable', 'ekomi-prc') ?></option>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="prc_shop_id"><?php echo __('Shop ID', 'ekomi-prc') ?></label>
                </th>
                <td>
                    <input type="text" name="prc_shop_id" id="prc_shop_id" value="<?php echo $data['prc_shop_id']; ?>">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="prc_shop_pw"><?php echo __('Shop Secret', 'ekomi-prc') ?></label>
                </th>
                <td>
                    <input type="text" name="prc_shop_pw" id="prc_shop_pw" value="<?php echo $data['prc_shop_pw']; ?>">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="prc_group_reviews" ><?php echo __('Group Reviews', 'ekomi-prc') ?></label>
                </th>
                <td>
                    <select name="prc_group_reviews" id="prc_group_reviews" value="<?php echo $data['prc_group_reviews'] ?>" >
                        <option value="0" <?php echo ($data['prc_group_reviews'] == 0) ? "selected=''" : "" ?> ><?php echo __('Disable', 'ekomi-prc') ?></option>
                        <option value="1" <?php echo ($data['prc_group_reviews'] == 1) ? "selected=''" : "" ?> ><?php echo __('Enable', 'ekomi-prc') ?></option>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="prc_no_review_msg"><?php echo __('Text when no reviews found', 'ekomi-prc') ?></label>
                </th>
                <td>
                    <input type="text" name="prc_no_review_msg" id="prc_no_review_msg" value="<?php echo $data['prc_no_review_msg']; ?>">
                </td>
            </tr>
            <tr valign="top">
                <th></th>
                <td class="ekomi-submit">
                    <?php wp_nonce_field('ekomi-prc-nonce'); ?>
                    <input type="submit" onclick="show_loader(this)" value="Save" class="button button-primary button-large">
                </td>
            </tr>
            <tr valign="top">
                <th></th>
                <td class="centered">
                    <div id="loader" style="display: none; position: absolute;left: 33%; width: 100%;">
                        <br/>
                        <br/>Loading Reviews ...
                        <br/>
                        <img src="assets/images/loader.gif"/>
                    </div>
                </td>
            </tr>
        </table>
    </form>

</div>

<script>
    function show_loader(obj) {
        document.getElementById("loader").style.display = "block";
        obj.disabled = true;
    }
</script>