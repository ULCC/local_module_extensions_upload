

M.local_module_extensions_upload = {

    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    local_module_extensions_upload_admin_page: function (e) {

        console.log($('#id_s_local_module_extensions_upload_importdata').prop('checked') + 'onload');

        if ($('#id_s_local_module_extensions_upload_dblocation').value == 'external') {
            console.log("checked");
            M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(false);
        } else {
            console.log("not checked");
            M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);
        }

        if ($('#id_s_local_module_extensions_upload_importdata').prop('checked')) {
            M.local_module_extensions_upload.local_module_extensions_upload_admin_page_settings_disabled_state(false);
            console.log("import data onload checked");
            if ($('#id_s_local_module_extensions_upload_dblocation').value == 'external') {
                console.log("checked");
                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(false);
            } else {
                console.log("not checked");
                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);
            }

        }   else    {
            console.log("import data onload not checked");
            M.local_module_extensions_upload.local_module_extensions_upload_admin_page_settings_disabled_state(true);

            if ($('#id_s_local_module_extensions_upload_dblocation').value == 'external') {
                console.log("checked");
                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(false);
            } else {
                console.log("not checked");
                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);
            }
        }


        $('#id_s_local_module_extensions_upload_importdata').change(function () {

            var isChecked = this.checked;

            if (this.checked) {

                console.log("checked");

                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_settings_disabled_state(false);

                if ($('#id_s_local_module_extensions_upload_dblocation').value == 'external') {
                    M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(false);

                } else {
                    M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);
                }

            } else {
                console.log("not checked");

                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_settings_disabled_state(true);

            }


        });


        if ($('#id_s_local_module_extensions_upload_dblocation').value == 'local') {

            M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);

        }

        $('#id_s_local_module_extensions_upload_dblocation').change(function () {

            console.log("TEST");

            if (this.value == 'external') {
                console.log("checked");

                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(false);

            } else {
                console.log("not checked");

                M.local_module_extensions_upload.local_module_extensions_upload_admin_page_external_db_disabled_state(true);

            }
        });

    },

        local_module_extensions_upload_admin_page_settings_disabled_state: function (state)  {

            $('#id_s_local_module_extensions_upload_dblocation').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbtype').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbhost').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbuser').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbname').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbsybasequoting').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbsetupsql').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_debugdb').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_output_debug').prop('disabled', state);

            $('#id_s_local_module_extensions_upload_tablename').prop('disabled', state);

            $('#id_s_local_module_extensions_upload_id').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_user').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_course').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_assessment').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_date').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_timelimit').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_type').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_reason_code').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_reason_desc').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_action').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_timecreated').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_lognotificationusers').prop('disabled', state);



        },


        local_module_extensions_upload_admin_page_external_db_disabled_state: function (state) {

            $('#id_s_local_module_extensions_upload_dbtype').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbhost').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbuser').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbname').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbsybasequoting').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_dbsetupsql').prop('disabled', state);
            $('#id_s_local_module_extensions_upload_debugdb').prop('disabled', state);


        }


}