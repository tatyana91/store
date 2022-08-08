$(document).ready(function() {
    $('.product__description *').css('font-family','');

    $('.js-filter-value').on('click', function(){
        $('.js-apply-filters').click();
    });

    $('.js-sort').on('change', function(){
        $('.js-apply-filters').click();
    });

    $('.js-apply-filters').on('click', function() {
        var filter_values = [];
        $('.js-filter-value').each(function() {
            if ($(this).prop('checked')) {
                var char_id = $(this).attr('data-char_id');
                var char_value_id = $(this).attr('data-char_value_id');
                filter_values[char_id] = (filter_values[char_id]) || [];
                filter_values[char_id].push(char_value_id);
            }
        });

        var filter_chars = [];
        filter_values.forEach(function(element, index) {
            var str = element.join();
            filter_chars.push("p" + index + "=" + str);
        });

        var min_price = $('.js-min-price').val();
        var max_price = $('.js-max-price').val();
        filter_chars.push("min_price=" + min_price);
        filter_chars.push("max_price=" + max_price);

        var sort_class = ($('.sort_mobile').css('display') === 'none') ? '.sort' : '.sort_mobile';
        var sort = $(sort_class + ' .js-sort').val();
        filter_chars.push("sort=" + sort);

        var filters_url = filter_chars.join("&");

        if (filters_url) {
            window.location.href = window.location.origin + window.location.pathname + "?" + filters_url;
        }
        else {
            window.location.href = window.location.origin + window.location.pathname;
        }
    });

    $('.js-clear-filters').on('click', function() {
        window.location.href = window.location.origin + window.location.pathname;
    });

    $(document).on('click', '.js-add-to-cart', function(e) {
        e.preventDefault();
        var id = $(this).attr('data-id');
        var context = $(this);
        $.ajax({
            url: "/katalog/ajax",
            type: "POST",
            data: {
                "act": "add_to_cart",
                "id": id,
            },
            success: function(html) {
                var result = JSON.parse(html);
                if (result.error) {
                    alert("Ошибка добавления товара в корзину");
                }
                else {
                    context
                        .removeClass('js-add-to-cart')
                        .html("<a href='/cart'>Перейти в корзину</a>");
                    $('.js-card-inputs[data-id=' + id + ']').html(result.inputs);
                    $('.js-cart-count').html(result.count);
                }
            }
        });
    });

    function changeCartCount(context, e){
        var id = context.attr('data-id');
        var count = +$('.js-count', context).val()
        if (e.target.closest('.js-cart-inc')) {
            count += 1;
            $('.js-count', context).val(count);
        }
        if (e.target.closest('.js-cart-dec')) {
            count -= 1;
            count = (count < 1) ? 0 : count;
            $('.js-count', context).val(count);
        }

        //удаляем товар из корзины, увеличиваем или уменьшаем количество
        if (e.target.closest('.js-remove-from-cart-list')
            || e.target.closest('.js-cart-inc')
            || e.target.closest('.js-cart-dec')
            || e.target.closest('.js-remove-from-cart')
            || e.target.closest('.js-count')
        ) {
            if (e.target.closest('.js-remove-from-cart-list')
                || e.target.closest('.js-remove-from-cart')) {
                count = 0;
            }

            $.ajax({
                url: "/katalog/ajax",
                type: "POST",
                data: {
                    "act": "change_cart_count",
                    "id": id,
                    "count": count,
                },
                success: function(html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        alert(result.error);
                    }
                    else {
                        if (context.is('.card') || context.is('.product')) {
                            if (count === 0) {
                                $('.js-card-btn', context)
                                    .html("В корзину")
                                    .removeClass('js-remove-from-cart')
                                    .addClass('js-add-to-cart');
                                $('.js-card-inputs', context).html('');
                                $('.js-cart-count').html(result.count);
                            }
                        }
                        else {
                            if (result.count === 0) {
                                location.reload();
                            }
                            else {
                                if (count === 0) {
                                    context.remove();
                                }
                                else {
                                    $('.js-item-cost', context).html(result.current_product_cost);
                                }

                                $('.js-cart-cost').html(result.cart_cost);
                                $('.js-delivery-cost').html(result.delivery_cost);
                                $('.js-total-cost').html(result.total_cost);
                                $('.js-cart-count').html(result.count);
                            }
                        }
                    }
                }
            });
            e.preventDefault();
        }
    }

    //КОРЗИНА клик
    $('.js-cart-item').on('click', function(e) {
        changeCartCount($(this), e);
    });
    //КОРЗИНА изменение
    $('.js-cart-item').on('change', function(e) {
        changeCartCount($(this), e);
    });

    $('.js-apply-promo').on('click', function() {
        var promo = $('input[name=promo]').val();
        if (promo) {
            $.ajax({
                url: "/katalog/ajax",
                type: "POST",
                data: {
                    "act": "apply_promo",
                    "promo": promo,
                },
                success: function(html) {
                    var result = JSON.parse(html);
                    $('.js-promo-result').removeClass('success').removeClass('error');
                    if (result.error) {
                        $('.js-promo-result')
                            .html(result.error)
                            .addClass('error');
                    }
                    else {
                        $.each(result.discount_products, function( id, discount ) {
                            $('.js-discount-price[data-id='+id+']').html('-' + discount + ' руб.');
                        });

                        $('.js-total-cost').html(result.total_cost);
                        $('.js-cart-cost').html(result.cart_cost);
                        $('.js-discount').html('-' + result.discount);
                        $('.js-discount-block').show();

                        $('.js-promo-result')
                            .html(result.text)
                            .addClass('success');
                    }
                }
            });
        }
    });

    $('.js-create-order').on('click', function() {
        var btn = $(this);
        btn.attr('disabled', true);
        var loader = $('.js-checkout-loader');
        loader.show();

        var error = '';
        $('.js-require-field').each(function() {
            if (!$(this).val()) {
                error += 'Заполните обязательные поля<br>';
                return false;
            }
        });

        var agree = $('.js-agree').prop('checked');
        if (!agree) {
            error += "Необходимо ваше согласие на обработку персональных данных<br>";
        }

        var email = $('input[name=email]').val();
        if (email && !validateEmail(email)) {
            error += "В почтовом адресе обнаружены ошибки<br>";
        }

        var pvz_id = 0;
        var address_id = 0;
        var delivery_type = $('.js-checkout-receiving.switch__title_active').attr('data-type');
        var payment_type = $('input[name=payment-type]:checked').val();
        if (delivery_type === 'pickup') {
            pvz_id = $('input[name=pvz]:checked').val();

            if (payment_type === '1') {
                error += "Самовывоз доступен только для заказов с онлайн оплатой.<br>";
                error += " Пожалуйста, выберите способ оплаты \"Банковской картой онлайн\".<br>";
                error += " Приносим извинения за временные неудобства.";
            }
        }
        else {
            address_id = $('.js-saved-address:checked').val();
            if (!address_id) {
                $('.js-require-address-field').each(function(){
                    if (!$(this).val()) {
                        error += 'Заполните обязательные поля адреса<br>';
                        return false;
                    }
                });
            }

            var region = (address_id > 0) ? $('.js-saved-address:checked').attr('data-region') : $('select[name=region]').val();
            if (payment_type === '2' && region !== '1') {
                var phone = $('.js-settings-phone').text();
                error += "Онлайн оплата доступна только для заказов по Санкт-Петербургу.<br>";
                error += " Пожалуйста, выберите другой способ оплаты.<br>";
                error += " <span style='color: #000'>Стоимость доставки по Лен. области можно уточнить по номеру <a href='tel:" + phone.replace(/\s/g, '') + "' class='button_link'>" + phone + "</a>.</span><br>";
            }
        }

        if (error) {
            $('.js-checkout-error').html(error);
            $(this).attr('disabled', false);
            loader.hide();
            return false;
        }

        var data = {};
        data.name = $('input[name=name]').val();
        data.phone = $('input[name=phone]').val();
        data.email = $('input[name=email]').val();
        data.comment = $('textarea[name=comment]').val();
        if (!pvz_id && !address_id) {
            data.region = region;
            data.city = $('input[name=city]').val();
            data.street = $('input[name=street]').val();
            data.house = $('input[name=house]').val();
            data.corpus = $('input[name=corpus]').val();
            data.building = $('input[name=building]').val();
            data.flat = $('input[name=flat]').val();
            data.entrance = $('input[name=entrance]').val();
            data.floor = $('input[name=floor]').val();
        }
        if (!pvz_id) {
            data.delivery_date = $('input[name=date]').val();
            data.delivery_time = $('select[name=time]').val();
        }
        data.payment_type = payment_type;
        data.address_id = address_id;
        data.pvz_id = pvz_id;
        data.promo = $('input[name=promo]').val();

        $.ajax({
            url: "/katalog/ajax",
            type: "POST",
            data: {
                "act": "create_order",
                "data": data,
            },
            success: function(html) {
                var result = JSON.parse(html);
                var $checkOutForm = $('.js-checkout-form');
                if (result.error) {
                    $checkOutForm.html(result.error);
                    btn.removeAttr('disabled');
                    loader.hide();
                }
                else {
                    $checkOutForm.html(result.html);
                    $('.js-btn-up').click();

                    if (result.payment_link) {
                        var timeout = 16;
                        var timeInterval = setInterval(function() {
                            if (timeout <= 0) {
                                clearInterval(timeInterval);
                                window.location = result.payment_link;
                                return true;
                            }
                            else {
                                timeout--;
                                let text = timeout + ' ';
                                switch (timeout) {
                                    case 0: text += 'секунд'; break;
                                    case 1: text += 'секунду'; break;
                                    case 2: case 3: case 4: text += 'секунды'; break;
                                    default: text += 'секунд'; break;
                                }
                                $('.js-checkout-timer').text(text);
                            }
                        }, 1000);
                    }
                }
            }
        });
    });

    function getVals() {
        // Get slider values
        var parent = this.parentNode;
        var slides = parent.getElementsByTagName("input");
        var slide1 = parseFloat(slides[0].value);
        var slide2 = parseFloat(slides[1].value);
        if (slide1 > slide2) {
            var tmp = slide2;
            slide2 = slide1;
            slide1 = tmp;
        }

        var displayElement1 = parent.getElementsByClassName("rangeValue")[0];
        var displayElement2 = parent.getElementsByClassName("rangeValue")[1];
        displayElement1.innerHTML = slide1;
        displayElement2.innerHTML = slide2;
    }

    window.onload = function() {
        var sliderSections = document.getElementsByClassName("range-slider");
        for (var x = 0; x < sliderSections.length; x++) {
            var sliders = sliderSections[x].getElementsByTagName("input");
            for (var y = 0; y < sliders.length; y++) {
                if (sliders[y].type === "range") {
                sliders[y].oninput = getVals;
                sliders[y].oninput();
                }
            }
        }
    };

    var auth = document.querySelector('.auth');
    var reg = document.querySelector('.reg');
    var reset = document.querySelector('.reset');
    var fullMenuBtn = document.getElementById('full-menu-btn');
    var fullMenu = document.getElementById('full-menu');
    var mobileMenu = document.querySelector('.mobile-menu');
    var priceForm = document.querySelector('.priceForm');
    var preorder = document.querySelector('.preorder');

    /*fullMenuBtn.addEventListener('click', function(){
    fullMenu.classList.add('active');
    });*/

    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.js-auth-popup')) {
            e.preventDefault();
            auth.classList.add('active');
        }
        if (e.target.closest('.auth__reg-link')) {
            e.preventDefault();
            reg.classList.add('active');
            auth.classList.remove('active');
            reset.classList.remove('active');
        }
        if (e.target.closest('.reg__auth-link')) {
            e.preventDefault();
            reg.classList.remove('active');
            auth.classList.add('active');
            reset.classList.remove('active');
        }
        if (e.target.closest('.reset__auth-link')) {
            e.preventDefault();
            reg.classList.remove('active');
            auth.classList.remove('active');
            reset.classList.add('active');
            $('.js-reset-email').val($('.js-login-email').val());
        }
        if (e.target.closest('.menu-mobile-toggle')) {
            mobileMenu.classList.add('active');
            document.body.classList.add('noscroll');
        }

        if (e.target.closest('.js-price-popup')) {
            priceForm.classList.add('active');
        }

        if (e.target.closest('.js-preorder-popup')) {
            preorder.classList.add('active');
            $('.js-preorder-product', preorder)
                .attr('data-id', $(e.target).attr('data-id'))
                .attr('href', $(e.target).attr('data-href'))
                .html($(e.target).attr('data-title'))
            ;
            e.preventDefault();
        }

        if (e.target.closest('.mobile-menu__close')) {
            mobileMenu.classList.remove('active');
            document.body.classList.remove('noscroll');
        }

        if (e.target.closest('.js-reg-close')
            || e.target.closest('.js-auth-close')
            || e.target.closest('.js-reset-close')
            || e.target.closest('.js-priceform-close')
            || e.target.closest('.js-preorder-close')
        ) {
            reg.classList.remove('active');
            auth.classList.remove('active');
            reset.classList.remove('active');
            priceForm.classList.remove('active');
            preorder.classList.remove('active');
        }

        if (fullMenu.classList.contains('active') && e.target.closest('#full-menu')) {
            return false;
        }
        else if (e.target.closest('#full-menu-btn')) {
            fullMenu.classList.contains('active') ? fullMenu.classList.remove('active') : fullMenu.classList.add('active');
        }
        else {
            fullMenu.classList.remove('active');
        }
    });

    $(function() {
        $(window).scroll(function() {
            if ($(this).scrollTop() != 0) {
                $('.js-btn-up').fadeIn();
            } else {
                $('.js-btn-up').fadeOut();
            }
        });

        $('.js-btn-up').click(function() {
            $('body,html').animate({
                scrollTop: 0
            }, 800);
        });
    });

    var tablinks = document.querySelectorAll('.tab-link');
    var tabs = document.querySelectorAll('.tab');

    tablinks.forEach(function(tablink) {
        var i = tablink.dataset.tab;
        tablink.addEventListener('click', function() {
            tablinks.forEach(function(all) {
                all.dataset.tab === i ? all.classList.add('active') : all.classList.remove('active')
            })
            tabs.forEach(function(tab) {
                tab.dataset.tabPane === i ? tab.classList.add('active') : tab.classList.remove('active')
            })
        })
    });

    $('.js-save-data').on("click", function() {
        $('.js-save-data-error').html('');
        $('.js-save-data-result').html('');
        var error = '';
        $('.js-require').each(function() {
            if (!$(this).val()) {
                error += "Укажите обязательный данные";
                return false;
            }
        });

        if (error) {
            $('.js-save-data-error').html(error);
            return false;
        }

        var data = {};
        data.name = $('input[name=name]').val();
        data.phone = $('input[name=phone]').val();
        data.connect_type = $('input[name=connect-type]:checked').val();
        data.subscribe = +$('input[name=subscribe]').prop('checked');

        $.ajax({
            url: "/lk/ajax",
            type: "POST",
            data: {
                "act": "save_lk_main_data",
                "data": data,
            },
            success: function(html) {
                var result = JSON.parse(html);
                if (result.error) {
                    $('.js-save-data-error').html(result.error);
                }
                else {
                    $('.js-save-data-result').html("Сохранено");
                }
            }
        });
    });

    $('.js-add-address-block').on('click', function() {
        $('.js-result-address-error').html('');
        $.ajax({
            url: "/lk/ajax",
            type: "POST",
            data: {
                "act": "add_address_block"
            },
            success: function(html) {
                var result = JSON.parse(html);
                if (result.error) {
                    $('.js-result-address-error').html(result.error);
                }
                else {
                    $(result.html).insertBefore('.js-result-address-error');
                }
            }
        });
    });

    $('.js-save-data-address').on("click", function() {
        $('.js-result-address-error').html('');
        $('.js-result-address-result').html('');
        var error = '';
        $('.js-required').each(function() {
          if (!$(this).val()) {
            error = "Заполните обязательные поля<br>";
          }
        });

        if (error){
            $('.js-result-address-error').html(error);
            return false;
        }

        var addresses = [];
        $('.js-address').each(function() {
            var address = {};
            address.region = $('select[name=region]', $(this)).val();
            address.city = $('input[name=city]', $(this)).val();
            address.street = $('input[name=street]', $(this)).val();
            address.house = $('input[name=house]', $(this)).val();
            address.corpus = $('input[name=corpus]', $(this)).val();
            address.building = $('input[name=building]', $(this)).val();
            address.flat = $('input[name=flat]', $(this)).val();
            address.entrance = $('input[name=entrance]', $(this)).val();
            address.floor = $('input[name=floor]', $(this)).val();
            address.id = $(this).attr('data-id');

            addresses.push(address);
        });

        $.ajax({
            url: "/lk/ajax",
            type: "POST",
            data: {
                "act": "save_addresses",
                "addresses": addresses
            },
            success: function(html) {
                var result = JSON.parse(html);
                if (result.error) {
                    $('.js-result-address-error').html(result.error);
                }
                else {
                    var i = 0;
                    $('.js-address').each(function() {
                    if (!$(this).attr('data-id')) {
                        $(this).attr('data-id', result.addresses[i].id)
                        $('.js-delete-address', $(this)).attr('data-id', result.addresses[i].id)
                    }
                    i++;
                    });

                    $('.js-result-address-result').html('Сохранено');
                }
            }
        });
    });

    $(document).on('click', '.js-delete-address', function() {
        $('.js-result-address-error').html('');
        var id = $(this).attr('data-id');
        if (id) {
            $.ajax({
            url: "/lk/ajax",
            type: "POST",
            data: {
              "act": "delete_address",
              "id": id
            },
            success: function(html) {
                var result = JSON.parse(html);
                if (result.error) {
                    $('.js-result-address-error').html(result.error);
                }
                else {
                    $('.js-address[data-id=' + id + ']').remove();
                }
            }
            });
        }
        else {
            $(this).parent().remove();
        }
    });

    $(document).on('click', '.js-show-move-products', function() {
        var products_count = $(this).attr('data-products_count');
        var block = $(this).attr('data-block');
        var goods = $(this).attr('data-goods');
        var category = $(this).attr('data-category');
        var context = $(this);
        $.ajax({
            url: "/index/ajax",
            type: "POST",
            data: {
                "act": "show_move_products",
                "category": category,
                "block": block,
                "goods": goods,
            },
            success: function(html) {
                var result = JSON.parse(html);
                $('.js-category[data-id="' + category + '"]').append(result.html);
                block++;
                if (block * 5 >= products_count) {
                    context.remove();
                }
                else {
                    context.attr("data-block", block);
                }
            }
        });
    });

    function validateEmail(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    $(document).on('click', '.js-req', function(){
      $('.js-req-error').html('');
      var error = '';

      var email = $('.js-reg-email').val();
      var phone = $('.js-reg-phone').val();
      var password = $('.js-reg-password').val();
      var password2 = $('.js-reg-password2').val();
      var agree = $('.js-agree').prop('checked');
      var subscribe = $('.js-subscribe').prop('checked');

      if (!validateEmail(email)) {
          error = (!email) ? 'Укажите эл. почту' : 'В почтовом адресе обнаружены ошибки';
      }
      else if (!password) {
          error = 'Укажите пароль';
      }
      else if (!password2) {
          error = 'Подтвердите пароль';
      }
      else if (password !== password2) {
          error = 'Пароли не совпадают';
      }
      else if (!agree) {
          error = 'Необходимо ваше согласие на обработку персональных данных';
      }

      if (error) {
        $('.js-req-error').html(error);
      }
      else {
          $.ajax({
              url: "/lk/ajax",
              type: "POST",
              data: {
                  "act": "req",
                  "email": email,
                  "phone": phone,
                  "password": password,
                  "subscribe": +subscribe
              },
              success: function (html) {
                  var result = JSON.parse(html);
                  if (result.error) {
                        $('.js-req-error').html(result.error);
                  }
                  else {
                        $('.js-reg-form').html(result.result);
                  }
              }
          });
      }
    });

    $(document).on('click', '.js-login', function(){
        $('.js-login-error').html('');
        var error = '';

        var email = $('.js-login-email').val();
        var password = $('.js-login-password').val();

        if (!validateEmail(email)) {
            error = (!email) ? 'Укажите эл. почту' : 'В почтовом адресе обнаружены ошибки';
        }
        else if (!password) {
            error = 'Укажите пароль';
        }

        if (error) {
            $('.js-login-error').html(error);
        }
        else {
            $.ajax({
                url: "/lk/ajax",
                type: "POST",
                data: {
                    "act": "login",
                    "email": email,
                    "password": password
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        $('.js-login-error').html(result.error);
                    }
                    else {
                        window.location.href = "/lk";
                    }
                }
            });
        }
    });

    $(document).on('click', '.js-reset', function(){
        $('.js-reset-error').html('');
        var error = '';

        var email = $('.js-reset-email').val();
        if (!validateEmail(email)) {
            error = (!email) ? 'Укажите эл. почту' : 'В почтовом адресе обнаружены ошибки';
        }

        if (error) {
            $('.js-reset-error').html(error);
        }
        else {
            $.ajax({
                url: "/lk/ajax",
                type: "POST",
                data: {
                    "act": "reset",
                    "email": email
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        $('.js-reset-error').html(result.error);
                    }
                    else {
                        $('.js-reset-success').html(result.success);
                    }
                }
            });
        }
    });

    $(document).on('click', '.js-request-price', function(){
        $('.js-priceform-error').html('');
        var error = '';

        var email = $('.js-priceform-email').val();
        if (!validateEmail(email)) {
            error = (!email) ? 'Укажите эл. почту' : 'В почтовом адресе обнаружены ошибки';
        }

        if (error) {
            $('.js-priceform-error').html(error);
        }
        else {
            $.ajax({
                url: "/katalog/ajax",
                type: "POST",
                data: {
                    "act": "request_priceform",
                    "email": email,
                    "phone": $('.js-priceform-phone').val()
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        $('.js-priceform-error').html(result.error);
                    }
                    else {
                        $('.js-priceform-success').html(result.success);
                    }
                }
            });
        }
    });

    $(document).on('click', '.js-send-preorder', function(){
        $('.js-preorder-error').html('');
        var error = '';

        var phone = $('.js-preorder-phone').val();
        if (!phone) {
            error = 'Укажите телефон';
        }

        if (error) {
            $('.js-preorder-error').html(error);
        }
        else {
            $.ajax({
                url: "/katalog/ajax",
                type: "POST",
                data: {
                    "act": "send_preorder",
                    "phone": phone,
                    "name": $('.js-preorder-name').val(),
                    "product_id": $('.js-preorder-product').attr('data-id')
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        $('.js-preorder-error').html(result.error);
                    }
                    else {
                        $('.js-preorder-success').html(result.success);
                    }
                }
            });
        }
    });

    $(document).on('click', '.js-change-password', function(){
        var error_item = $('.js-change-error');
        error_item.html('');
        var error = '';

        var old_password = $('.js-old-password').val();
        var new_password = $('.js-new-password').val();
        var new_password2 = $('.js-new-password2').val();

        if (!old_password) {
            error = 'Укажите старый пароль';
        }
        else if (!new_password){
            error = 'Укажите новый пароль';
        }
        else if (!new_password2){
            error = 'Повторите новый пароль';
        }
        else if (new_password !== new_password2) {
            error = 'Новые пароли не совпадают';
        }

        if (error) {
            error_item.html(error);
        }
        else {
            $.ajax({
                url: "/lk/ajax",
                type: "POST",
                data: {
                    "act": "change_password",
                    "old_password": old_password,
                    "new_password": new_password
                },
                success: function (html) {
                    var result = JSON.parse(html);
                    if (result.error) {
                        error_item.html(result.error);
                    }
                    else {
                        $('.js-change-result').html(result.result);
                    }
                }
            });
        }
    });

    $(document).on("click", '.js-checkout-btn', function(e){
        $.ajax({
            url: "/katalog/ajax",
            type: "POST",
            data: {
                "act": "checkout_cart"
            },
            success: function (html) {
                var result = JSON.parse(html);
                if (result.error) {
                  alert(result.error);
                }
                else if (Object.keys(result.product_errors).length > 0) {
                    $('.js-count').each(function(){
                      var product_id = $(this).attr('data-product-id');
                      if (result.product_errors[product_id] !== undefined) {
                        $(this).addClass('error');
                        if (result.product_errors[product_id] < 0) {
                            result.product_errors[product_id] = 0;
                        }
                        $('.js-free-count[data-product-id='+product_id+']').html('доступно ' + result.product_errors[product_id] + "ед.");
                      }
                    });
                }
                else {
                    window.location.href = "/cart/checkout";
                }
            }
        });
    });

    $(document).on('click', '.js-show-filters', function(){
        var filters = $('.js-filters');
        if (filters.css('display') === 'none') {
            $(this).addClass('active');
            $(this).html('Свернуть параметры');
            filters.slideDown(400);
        }
        else {
            $(this).removeClass('active');
            $(this).html('Подобрать параметры');
            filters.slideUp(400);
        }
    });

    $(document).on('click', '.js-subfilters', function(e){
        if ($(e.target).is('.js-subfilter-btn')) {
            var subfilter = $('.js-subfilter', $(this));
            if (subfilter.css('display') === 'none') {
                $(e.target).addClass('active');
                subfilter.slideDown(400);
            }
            else {
                $(e.target).removeClass('active');
                subfilter.slideUp(400);
            }
        }
    });

    $(function() {
        if ($('.js-slider').children().length > 1) {
            $('.js-slider').on('init', function() {
                $('.slick-dots').on('click',function() {
                    $('.autoplay').slick('slickPause');
                });

                $('.slick-arrow').on('click',function() {
                    $('.autoplay').slick('slickPause');
                });
            }).slick({
                autoplay: true,
                autoplaySpeed: 5000,
                speed: 500,
                fade: true,
                cssEase: 'linear',
                dots: true,
                arrows: true,
                infinite: true,
                centerMode: true,
                centerPadding: "0px",
                responsive: [
                    {
                        breakpoint: 1280,
                        settings: {
                            arrows: false,
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true
                        }
                    }
                ]
            });
        }

        if ($('.jsCatalogSlider').children().length > 1) {
            $('.jsCatalogSlider').on('init', function(slick) {
                $('.jsCatalogSlider .slick-dots').on('click',function() {
                    $('.jsCatalogSlider .autoplay').slick('slickPause');
                });

                $('.jsCatalogSlider .slick-arrow').on('click',function() {
                    $('.jsCatalogSlider .autoplay').slick('slickPause');
                });
            }).slick({
                autoplay: true,
                autoplaySpeed: 5000,
                speed: 500,
                fade: true,
                cssEase: 'linear',
                dots: false,
                arrows: false,
                infinite: true,
                centerMode: true,
                centerPadding: "0px",
                responsive: [
                    {
                        breakpoint: 1280,
                        settings: {
                            arrows: false,
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: false
                        }
                    }
                ]
            });
        }
    });

    $(document).on('click', '.js-close-policy', function(){
        $.ajax({
            url: "/index/ajax",
            type: "POST",
            data: {
                "act": "accept_policy"
            },
            success: function (html) {
                $('.js-policy-info').remove();
            }
        });
    });

    $(document).on('click', '.js-show-order-details', function(){
        var details = $('.js-order-details', $(this).parents('.order'));
        if (details.is(':visible')) details.hide(300);
        else details.show(300);
    });

    $(function() {
        var disabled_dates = ["2021-01-01","2021-01-02","2021-01-03","2021-01-04"]
        $(".js-datepicker").datepicker({
            dateFormat: 'dd.mm.yy',
            minDate: $(".js-datepicker").val(),
            onSelect: (date) => getTimeOptions(date),
            beforeShowDay: function(date){
                var string = $.datepicker.formatDate('yy-mm-dd', date);
                return [ disabled_dates.indexOf(string) == -1 ]
            }
        });
    });

    function getTimeOptions(date) {
        $.ajax({
            url: "/katalog/ajax",
            type: "POST",
            data: {
                "act": 'get_delivery_options',
                "date": date
            },
            success: function (html) {
                var result = JSON.parse(html);
                $(".js-datepicker").val(result.day);
                $('select[name=time]').html(result.html);
            }
        });

        $(".js-datepicker").parent().parent().change();
    }

    if ($('.js-card-slider').children().length > 1) {
        $('.js-card-slider').slick({
            autoplay: false,
            fade: true,
            dots: true,
            arrows: true,
            infinite: true,
            centerMode: true,
            centerPadding: "0px",
            responsive: [
                {
                    breakpoint: 1280,
                    settings: {
                        arrows: false,
                        slidesToShow: 1,
                        slidesToScroll: 1,
                        infinite: true,
                        dots: true
                    }
                }
            ]
        });
    }

    $(document).on('click', '.js-checkout-receiving', function () {
        if ($(this).attr('data-type') === 'delivery') {
            $('[data-block=delivery]').show();
            $('[data-block=pickup]').hide();
        }
        else {
            $('[data-block=delivery]').hide();
            $('[data-block=pickup]').show();
        }

        $('.js-checkout-receiving').toggleClass('switch__title_active');
    });

    if ($('.js-apply-promo').length > 0) {
        $('.js-apply-promo').click();
    }

    if ($('.js-show-filters').css('display') === 'block') {
        var show_filters = false;
        $('.js-subfilters').each(function() {
            var block = $(this);

            $('.js-filter-value', block).each(function(){
                if ($(this).prop('checked')) {
                    $('.js-subfilter-btn', block).click();
                    show_filters = true;
                    return false;
                }
            });

            if ($('.js-sort', block).val() && $('.js-sort', block).val() !== '0') {
                $('.js-subfilter-btn', block).click();
                show_filters = true;
            }
        });

        if (show_filters) {
            $('.js-show-filters').click();
        }
    }

    $(document).on('click', '.js-menu-item', function(e){
        if ($(e.target).is('.js-menu-btn')) {
            var menu = $('.js-menu', $(this));
            if (menu.css('display') === 'none') {
                $(e.target).addClass('active');
                menu.slideDown(400);
            }
            else {
                $(e.target).removeClass('active');
                menu.slideUp(400);
            }
        }
    });
});
