$(document).ready(function(){
    tinyMCE.init({
        mode: "specific_textareas",
        editor_selector : "tinymce",
        theme:"modern",
        language:"ru",
        plugins: 'preview searchreplace autolink directionality code visualblocks visualchars fullscreen image link media codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime advlist lists textcolor wordcount imagetools  contextmenu colorpicker textpattern',
        toolbar1: 'insert | undo redo | formatselect | bold italic strikethrough forecolor backcolor | link | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent  | removeformat',
        height : "500",
        images_upload_url: '/admin/postacceptor',
        relative_urls: false,
        automatic_uploads: true,
        file_picker_types: 'image',
        file_picker_callback: function(cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.onchange = function() {
                var file = this.files[0];

                var reader = new FileReader();
                reader.onload = function () {
                    var id = 'blobid' + (new Date()).getTime();
                    var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                    var base64 = reader.result.split(',')[1];
                    var blobInfo = blobCache.create(id, file, base64);
                    blobCache.add(blobInfo);
                    cb(blobInfo.blobUri(), { title: file.name });
                };
                reader.readAsDataURL(file);
            };

            input.click();
        }
    });

    $(document).ready(function(){
        $('.details_table').click(function(){
            var id = $(this).attr('data-id');
            if ($('#details_table_'+id).css('display') == 'none') {
                $('.table_dt').hide();
                $('#details_table_'+id).show();
            }
            else {
                $('.table_dt').hide();
            }
        });
    });

    setTimeout("$('#notice').hide()", 5000);

    $('.js-ref-char-block').keyup(function(e){
        var context = $(this);
        var target = e.target;
        var result = $('.js-result', context);
        var char_id = context.attr('data-char_id');
        if (target.closest('.js-ref-char-value')) {
            var value = target.value;
            if (char_id && value) {
                $.ajax({
                    url: "/admin/ajax",
                    type: "POST",
                    data: {
                        'act': 'get_ref_char_values_list',
                        'value': value,
                        'char_id': char_id
                    },
                    cache: false,
                    success: function(html){
                        var answer = JSON.parse(html);
                        if (answer.error) {
                            alert(answer.error);
                        }
                        else if (answer.html){
                            result.show().html(answer.html);
                        }
                        else {
                            result.hide();
                        }
                    }
                });
            }
            else {
                result.hide();
            }
        }
    });

    $('.js-ref-char-block').click(function(e){
        var context = $(this);
        var target = e.target;
        if (target.closest('.js-check-value')){
            var char_value_title = target.innerText;
            $('.js-ref-char-value', context).val(char_value_title);
            $('.js-result', context).hide();
        }
    })

    $('.js-set-order-completed').click(function(){
        if (confirm("Вы уверены, что этот заказ выполнен?")) {
            var id = $(this).attr('data-id');
            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    'act': 'set_order_completed',
                    'id': id
                },
                cache: false,
                success: function(html){
                    var answer = JSON.parse(html);
                    if (answer.error) {
                        alert(answer.error);
                    }
                    else{
                        location.reload();
                    }
                }
            });
        }
    });

    $('.js-set-order-declined').click(function(){
        if (confirm("Вы уверены, что хотите отклонить этот заказ?")) {
            var id = $(this).attr('data-id');
            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    'act': 'set_order_declined',
                    'id': id
                },
                cache: false,
                success: function(html){
                    var answer = JSON.parse(html);
                    if (answer.error) {
                        alert(answer.error);
                    }
                    else{
                        location.reload();
                    }
                }
            });
        }
    });

    function fixWidthHelper(e, ui) {
        ui.children().each(function() {
            $(this).width($(this).width());
            $(this).height($(this).height());
        });
        return ui;
    }

    $( function() {
        var arrayRate = [];
        var table = '';
        $(".sortable").sortable({
            handle:'td:first-child',
            axis:'y',
            helper: fixWidthHelper,
            update: function(event, ui){
                table = $(".sortable").attr('data-table');
                var count = $(".js-item").length;
                $(".js-item").each(function(){
                    arrayRate[$(this).attr('data-id')] = count;
                    $(this).attr('data-id', count--);
                });
            },
            stop: function(event, ui){
                $.ajax({
                    type: 'POST',
                    url: '/admin/ajax',
                    data: {
                        'act' : 'update_rate',
                        'array_rate' : arrayRate,
                        'table' : table
                    },
                    success: function(data){
                        var data = JSON.parse(data);
                        if (data.error) {
                            alert(data.error);
                        }
                        else {
                            location.reload();
                        }
                    }
                });
            }
        });
        //$( ".sortable" ).disableSelection();
        $(".sortable").mousedown(function(){
            document.activeElement.blur();
        });
    });

    $(document).on('change', '.js-edit-order', function(e){
        var target = $(e.target);
        var field = target.attr('data-field');
        var value = target.val();
        var order_id = $(this).attr('data-order_id');

        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "edit_order",
                "order_id": order_id,
                "field": field,
                "value": value
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.success);
            }
        });
    });

    function validateEmail(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    $(document).on('click', '.js-send-edit-mail', function(){
        if (!confirm("Стоимость заказа пересчитана?")) {
            return;
        }

        var order_id = $(this).attr('data-order_id');
        var type = $(this).attr('data-type');

        if (type === 'user') {
            var email = $('.js-order-email').val();
            if (!validateEmail(email)) {
                alert('В почтовом адресе клиента обнаружены ошибки');
                return;
            }
        }

        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "send_edit_mail",
                "order_id": order_id,
                "type": type
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.success);
            }
        });
    });

    $(document).on('click', '.js-create-depot-products', function(){
        var $btn = $(this);
        var btn_text = $btn.html();
        $btn.html('Создаю...');
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "create_depot_products"
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.notice);
                $btn.html(btn_text);
            }
        });
    });

    $(document).on('change', '.js-save-page-bounds', function(){
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "save_page_bounds",
                "depot_id": $(this).attr('data-guid'),
                "depot_title": $(this).attr('data-title'),
                "page_id": $(this).val()
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.notice);
            }
        });
    });

    $(document).on('change', '.js-save-counterparty-bounds', function(){
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "save_counterparty_bounds",
                "depot_id": $(this).attr('data-guid'),
                "depot_title": $(this).attr('data-title'),
                "site_user_id": $(this).val()
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.notice);
            }
        });
    });

    $(document).on('change', '.js-save-product-bounds', function(){
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "save_product_bounds",
                "depot_id": $(this).attr('data-guid'),
                "depot_title": $(this).attr('data-title'),
                "product_id": $(this).val()
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) show_error(result.error);
                else show_success(result.notice);
            }
        });
    });

    $(document).on('click', '.js-update-depot-products', function(){
        var $btn = $(this);
        var btn_text = $btn.html();
        $btn.html('Обновляю...');
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "update_depot_products"
            },
            success: function (html) {
                var result = JSON.parse(html);
                console.dir(result);
                if (result.error) show_error(result.error);
                else show_success(result.notice);
                $btn.html(btn_text);
            }
        });
    });

    $(document).on('click', '.js-create-depot-order', function(){
        //создание заказа на складе
        var context = $(this);
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "create_depot_order",
                "order_id": context.attr('data-order_id')
            },
            success: function (html) {
                var result = JSON.parse(html);
                console.dir(result);
                if (result.error) show_error(result.error);
                else {
                    show_success(result.notice);
                    context.parent().remove();
                }
            }
        });
    });

    $(document).on('click', '.js-add-order-product', function(){
        var order_id = $(this).attr('data-order_id');
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "add_order_product",
                "order_id": order_id
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) {
                    show_error(result.error);
                }
                else {
                    $('.js-cart-products').append(result.html);
                }
            }
        });
    });

    $(document).on('change', '.js-cart-item', function(){
        var context = $(this);
        var order_product_id = $(this).attr('data-id');
        var product_id = $('.js-cart-item-product_id', context).val();
        var count = $('.js-cart-item-count', context).val();

        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "update_order_product",
                "order_product_id": order_product_id,
                "product_id": product_id,
                "count": count
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) {
                    show_error(result.error);
                }
                else {
                    $('.js-cart-item-price', context).html(result.order_product_info.price);
                    $('.js-cart-item-cost', context).html(result.order_product_info.cost);
                    $('.js-cart-item-count', context).val(result.order_product_info.count);

                    if (result.change_count){
                        $('.js-edit-success').html("Доступно " + result.order_product_info.count + " ед.");
                        $('.js-edit-success')[0].classList.add('visible');
                        setTimeout(function(){
                            $('.js-edit-success')[0].classList.remove('visible');
                        }, 3000)
                    }
                }
            }
        });
    });

    $(document).on('click', '.js-cart-item', function(event){
        var context = $(this);
        var target = $(event.target);
        var order_product_id = context.attr('data-id');
        if (target.is('.js-delete-cart-item')){
            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    "act": "delete_order_product",
                    "order_product_id": order_product_id
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        show_error(result.error);
                    }
                    else {
                        context.remove();
                    }
                }
            });
        }
    });

    $(document).on('click', '.js-edit-order', function(event){
        var context = $(this);
        var target = $(event.target);
        var order_id = context.attr('data-order_id');
        if (target.is('.js-calc-order')){
            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    "act": "calc_order",
                    "order_id": order_id
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        show_error(result.error);
                    }
                    else {
                        $('.js-order-cart-cost').html(result.cart_cost);
                        $('.js-order-total-cost').html(result.total_cost);
                        $('.js-order-delivery-cost').html(result.delivery_cost);
                    }
                }
            });
        }
    });


    $(document).on('change', '.js-item', function(e){
        var target = $(e.target);
        var context = $(this);
        if (target.is('.js-edit-product-field')){
            var field = target.attr('name');
            var product_id = context.attr('data-id');
            var value = (target.is(':checkbox')) ? +target.prop('checked') : target.val();

            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    "act": "edit_product_field",
                    "product_id": product_id,
                    "field": field,
                    "value": value
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) show_error(result.error);
                    else show_success(result.success);
                }
            });
        }
        else if (target.is('.js-edit-product-promo')){
            var field = target.attr('name');
            var product_id = context.attr('data-id');
            var value = [];
            $('.js-edit-product-promo', context).each(function(){
                if ($(this).prop('checked')) {
                    value.push($(this).attr('data-promo_id'))
                }
            });

            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    "act": "edit_product_field",
                    "product_id": product_id,
                    "field": field,
                    "value": value
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) show_error(result.error);
                    else show_success(result.success);
                }
            });
        }
        else if (target.is('.js-edit-site-user-field')){
            var field = target.attr('name');
            var site_user_id = context.attr('data-id');
            var value = target.val();
            $.ajax({
                url: "/admin/ajax",
                type: "POST",
                data: {
                    "act": "edit_site_user_field",
                    "site_user_id": site_user_id,
                    "field": field,
                    "value": value
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) show_error(result.error);
                    else show_success(result.success);
                }
            });
        }
    });

    $(document).on('keyup', '.js-seo-item input[name=title], .js-seo-item textarea[name=description], .js-seo-item textarea[name=keywords]', function(){
        $(this).prev().find('span').html($(this).val().length);
    });

    $(function() {
        $(".js-datepicker").datepicker({
            dateFormat: 'dd.mm.yy',
            onSelect: function(date) {
                $.ajax({
                    url: "/katalog/ajax",
                    type: "POST",
                    data: {
                        "act": 'get_full_delivery_options',
                        "date": 0
                    },
                    success: function (html) {
                        var result = JSON.parse(html);
                        $('select[name=time]').html(result.html);
                    }
                });

                $(".js-datepicker").change();
                $("select[name=time]").change();
            }
        });
    });

    function show_error(text){
        $('.js-edit-error').html(text);
        $('.js-edit-error')[0].classList.add('visible');
        setTimeout(function(){
            $('.js-edit-error')[0].classList.remove('visible');
        }, 3000)
    }

    function show_success(text){
        $('.js-edit-success').html(text);
        $('.js-edit-success')[0].classList.add('visible');
        setTimeout(function(){
            $('.js-edit-success')[0].classList.remove('visible');
        }, 3000)
    }

    $(document).on('click', '.js-change-temp-status', function(){
        var statuses = [];
        $('input[name^="statuses"]:checked').each(function(){
            statuses.push($(this).val());
        });
        statuses = statuses.join(",");
        $.ajax({
            url: "/admin/ajax",
            type: "POST",
            data: {
                "act": "update_temp_statuses",
                "statuses": statuses
            },
            success: function () {
                window.location.reload();
            }
        });
    });

    $('.panel-heading').click(function () {
        $(this).toggleClass('in').next().slideToggle();
        //$('.panel-heading').not(this).removeClass('in').next().slideUp();
    });
});

