/* Surveyors Module JS */

function validate_surveyor_form(selector) {
    selector = typeof selector == 'undefined' ? '#surveyor-form' : selector;

    appValidateForm($(selector), {
        date:   'required',
        number: { required: true },
    });

    $('body').find('input[name="number"]').rules('add', {
        remote: {
            url:  admin_url + 'surveyors/validate_surveyor_number',
            type: 'post',
            data: {
                number: function() {
                    return $('input[name="number"]').val();
                },
                isedit: function() {
                    return $('input[name="number"]').data('isedit');
                },
                original_number: function() {
                    return $('input[name="number"]').data('original-number');
                },
                date: function() {
                    return $('body').find('.surveyor-form input[name="date"]').val();
                },
            },
        },
        messages: {
            remote: app.lang.estimate_number_exists,
        },
    });
}

function surveyor_mark_status(e, id, status) {
    e.preventDefault();
    $.post(admin_url + 'surveyors/mark_action_status/' + status + '/' + id, function(resp) {
        if (resp.success) {
            alert_float('success', resp.message);
            $('#surveyor-status-badge').html(resp.status_html);
            $('li[data-mark-status]').show();
            $('li[data-mark-status="' + status + '"]').hide();
            if ($.fn.DataTable.isDataTable('#surveyors')) {
                $('#surveyors').DataTable().ajax.reload(null, false);
            }
        } else {
            alert_float('danger', resp.message);
        }
    }, 'json');
}

function surveyor_pipeline() {
    init_kanban(
        'surveyors/get_pipeline',
        surveyors_pipeline_update,
        '.pipeline-status',
        290,
        360
    );
}

function surveyors_pipeline_sort(type) {
    kan_ban_sort(type, surveyor_pipeline);
}

function surveyors_pipeline_update(ui, object) {
    if (object === ui.item.parent()[0]) {
        var data = {
            surveyorid: $(ui.item).attr('data-surveyor-id'),
            status: $(ui.item.parent()[0]).attr('data-status-id'),
            order: [],
        };

        $.each($(ui.item).parents('.pipeline-status').find('li'), function(idx, el) {
            var id = $(el).attr('data-surveyor-id');
            if (id) {
                data.order.push([id, idx + 1]);
            }
        });

        check_kanban_empty_col('[data-surveyor-id]');

        setTimeout(function() {
            $.post(admin_url + 'surveyors/update_pipeline', data).done(function(response) {
                update_kan_ban_total_when_moving(ui, data.status);
                surveyor_pipeline();
            });
        }, 500);
    }
}

function surveyor_pipeline_open(id) {
    if (id === '') {
        return;
    }
    requestGet('surveyors/pipeline_open/' + id).done(function(response) {
        var visible = $('.surveyor-pipeline:visible').length > 0;
        $('#surveyor').html(response);
        if (!visible) {
            $('.surveyor-pipeline').modal({
                show: true,
                backdrop: 'static',
                keyboard: false,
            });
        } else {
            $('#surveyor')
                .find('.modal.surveyor-pipeline')
                .removeClass('fade')
                .addClass('in')
                .css('display', 'block');
        }
    });
}

function init_surveyor_notes() {
    $("body").off("submit", "#surveyor-notes").on("submit", "#surveyor-notes", function () {
        var form = $(this);
        var description = form.find('textarea[name="description"]').val().trim();
        if (description === "") {
            return false;
        }
        $.post(form.attr("action"), form.serialize()).done(function (rel_id) {
            form.find('textarea[name="description"]').val("");
            get_sales_notes(rel_id, "surveyors");
        });
        return false;
    });
}

function init_surveyor(id) {
    load_small_table_item(
        id,
        "#surveyor",
        "surveyorid",
        "surveyors/get_surveyor_data_ajax",
        ".table-surveyors"
    );
}

function init_surveyor_equipment_select() {
    var $sel = $('#equipment_select');
    if (!$sel.length) { return; }

    $sel.on('change', function() {
        var id = $(this).val();
        if (!id) { return; }

        $.getJSON(admin_url + 'surveyors/get_equipment_data/' + id, function(eq) {
            if (!eq || !eq.id) { return; }

            // Prevent duplicate
            if ($('#surveyor-equipment-tbody tr[data-id="' + eq.id + '"]').length) {
                alert_float('warning', 'Equipment already added.');
                $sel.selectpicker('val', '');
                return;
            }

            var idx = $('#surveyor-equipment-tbody tr').length;
            var row = '<tr class="surveyor-equipment-row" data-id="' + eq.id + '">'
                + '<td>' + $('<span>').text(eq.item_name).html()
                + '<input type="hidden" name="equipment[' + idx + '][surveyor_equipment_id]" value="' + eq.id + '"></td>'
                + '<td>' + $('<span>').text(eq.unit_code).html() + '</td>'
                + '<td>' + $('<span>').text(eq.serial_number || '').html() + '</td>'
                + '<td>' + $('<span>').text(eq.location || '').html() + '</td>'
                + '<td>' + $('<span>').text(eq.cert_expired_date || '').html() + '</td>'
                + '<td><a href="#" class="btn btn-danger btn-sm surveyor-equipment-remove" data-id="' + eq.id + '">'
                + '<i class="fa fa-times"></i></a></td>'
                + '</tr>';

            $('#surveyor-equipment-tbody').append(row);
            $sel.selectpicker('val', '');
        });
    });

    $(document).off('click', '.surveyor-equipment-remove').on('click', '.surveyor-equipment-remove', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        // Re-index hidden inputs
        $('#surveyor-equipment-tbody tr').each(function(i) {
            $(this).find('input[type="hidden"]').attr('name', 'equipment[' + i + '][surveyor_equipment_id]');
        });
    });
}

function init_surveyors_total(manual) {
    if ($("#surveyors_total").length === 0) {
        return;
    }
    var _quo_total_href_manual = $(".surveyors-total");
    if (
        $("body").hasClass("surveyors-total-manual") &&
        typeof manual == "undefined" &&
        !_quo_total_href_manual.hasClass("initialized")
    ) {
        return;
    }
    _quo_total_href_manual.addClass("initialized");
    var currency = $("body").find('select[name="total_currency"]').val();
    var _years = $("body")
        .find('select[name="surveyors_total_years"]')
        .selectpicker("val");
    var years = [];
    $.each(_years, function (i, _y) {
        if (_y !== "") {
            years.push(_y);
        }
    });

    var surveyor_id = "";
    var project_id = "";

    var _surveyor_id = $('.surveyor_profile input[name="userid"]').val();
    var _project_id = $('input[name="project_id"]').val();
    if (typeof _surveyor_id != "undefined") {
        surveyor_id = _surveyor_id;
    } else if (typeof _project_id != "undefined") {
        project_id = _project_id;
    }

    $.post(admin_url + "surveyors/get_surveyors_total", {
        currency: currency,
        init_total: true,
        years: years,
        surveyor_id: surveyor_id,
        project_id: project_id,
    }).done(function (response) {
        $("#surveyors_total").html(response);
    });
}
