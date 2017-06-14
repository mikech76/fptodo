// Server-Side Event
var evtSource = null;
// авторизованный юзер
var current_user = null;
// текущий список
var current_todoList_id = null;


(function (window) {
    'use strict';


    // идентификация пользователя

    function userAuth(data) {

        if (data.user) {
            //авторизован
            errorlog();

            current_user = data.user;
            current_todoList_id = null;

            $('#user-panel h1').html('<i class="fa fa-user-circle" aria-hidden="true"></i> ' + current_user.login);
            $('.user_on').show();
            $('.user_off').hide();

            // открываем SSE канал
            startSSE();
        }
        if (data.error) {
            // не авторизован
            errorlog(data.error);

            current_user = null;
            current_todoList_id = null;

            $('.user_on').hide();
            $('.user_off').show();

            stopSSE();
        }
    }

    todoPost({}, userAuth);


    // init Interface
    initInterface();

    /**
     * обработчики приложения
     */
    function initInterface() {
        // -------------------------- Юзер ----------------------------------------
        // ==========================  Форма Авторизации ==========================
        $("#login-form form").submit(function (e) {
            e.preventDefault();
            var form = $(e.target);
            var user_login = form.children('[name=user_login]').val();
            if (user_login.length < 4) {
                userAuth({error: {user_login_small: "Имя пользователя мало."}});
                return;
            }
            var user_password = form.children('[name=user_password]').val();

            if (user_password.length < 3) {
                userAuth({error: {passwords_dont_coincide: "Пароли не совпадают"}});
                return;
            }
            todoPost({
                action: 'user_login',
                user_login: user_login,
                user_password: user_password
            }, userAuth);
        });

        // ==========================  Выход ======================================
        $("#logout").click(function () {
            document.cookie = "PHPSESSID=;  path=/; expires=" + (new Date(0)).toUTCString();
            userAuth();
        });

        // ==========================  Форма Регистрации ==========================
        $("#register-form form").submit(function (e) {
            e.preventDefault();
            var form = $(e.target);
            var user_login = form.children('[name=user_login]').val();
            if (user_login.length < 4) {
                userAuth({error: {user_login_small: "Имя пользователя мало."}});
                return;
            }
            var user_password = form.children('[name=user_password]').val();
            var user_password2 = form.children('[name=user_password2]').val();

            if (user_password.length < 3 || user_password != user_password) {
                userAuth({error: {passwords_dont_coincide: "Пароли не совпадают"}});
                return;
            }

            todoPost({
                action: 'user_register',
                user_login: user_login,
                user_password: user_password
            }, userAuth);
        });

        // -------------------------- Списки ---------------------------------------

        $("#list-panel form").submit(function (e) {
            e.preventDefault();

        });

        // ==========================  Выбор списка задач ==========================
        $("#todolists").dblclick(function (e) {
            var opt = $('#todolists option:selected');
            if (current_todoList_id != opt.val()) {
                // этот список не выбран

                current_todoList_id = opt.val();
                // очистить шары
                $("#shares option").remove();
                // новый канал
                startSSE();
            }
            opt.removeClass('updated');
        });

        // =====================  Редактирование списка задач ======================
        // выбор
        $("#todolists").click(function () {
            var selected = $(this).find(':selected');
            $("#todolist_editor").val(selected.text()).attr('data-todolist_id', selected.val());
        });

        // создать список
        // http://mikech.zapto.org/fptodo/?route=post&action=todolist_create&todolist_name=На%20пикник
        $("#todolist_add").click(function () {
            var todolist_name = $("#todolist_new").val();
            if (todolist_name.length) {
                todoPost({
                    action: 'todolist_create',
                    todolist_name: todolist_name
                }, startSSE);
            } else {
                errorlog({error: 'Имя списка не может быть пустым!'});
            }
        });

        // переименовать
        $("#todolist_edit").click(function () {
            var todolist_id = $("#todolist_editor").attr('data-todolist_id');
            var todolist_name = $("#todolist_editor").val();

            if (todolist_name.length && todolist_id) {
                todoPost({
                    action: 'todolist_update',
                    todolist_id: todolist_id,
                    todolist_name: todolist_name
                }, startSSE);
            } else {
                errorlog({error: 'Имя списка не может быть пустым!'});
            }
        });

        // удалить -> у шары статус 0
        $("#todolist_edit_delete").click(function () {
            var todolist_id = $("#todolist_editor").attr('data-todolist_id');
            var user_login = current_user.login;

            if (todolist_id == current_todoList_id) { //  111111
                current_todoList_id = null;
                // очистить шары
                $("#shares option").remove();
            }
            todoPost({
                action: 'todolist_share',
                share_user_login: user_login,
                share_todolist_id: todolist_id,
                share_mode: 0 // delete
            }, startSSE);





            // удалить список из селекта
            $("#todolists option[value=" + todolist_id + "]").remove();
        });

        // -------------------------- Шары ----------------------------------------

        $("#share-panel form").submit(function (e) {
            e.preventDefault();
        });

        // =====================  Редактирование шары ======================
        // выбор
        $("#shares").click(function () {
            var selected = $(this).find(':selected');
            $("#share_editor")
                .val(selected.text())
                .attr('data-user_login', selected.text())
                .attr('data-share_id', selected.val());

            switch (selected.attr('data-share_mode')) {
                case "1": // owner - нельзя
                    $(this).hide().next().hide();
                    break;

                case "2": // edit
                    $("#share_edit_edit").hide();
                    $("#share_edit_see").show();
                    break;

                case "3": // see
                    $("#share_edit_edit").show();
                    $("#share_edit_see").hide();
            }
            $("#share_edit_delete").show();
        });

        function shareSetMode(user_login, mode) {
            if (!user_login.length) {
                return;
            }

            todoPost({
                action: 'todolist_share',
                share_user_login: user_login,
                share_todolist_id: current_todoList_id,
                share_mode: mode
            }, startSSE);

            $("#share_editor, #share_new").val("");
            $("#share_edit_edit, #share_edit_see, #share_edit_delete").hide();
        }

        // переключить в режим чтения
        $("#share_edit_edit").click(function () {
            shareSetMode($("#share_editor").attr('data-user_login'), 2);
        });

        // переключить в режим редактирования
        $("#share_edit_see").click(function () {
            shareSetMode($("#share_editor").attr('data-user_login'), 3);
        });

        // удалить шару
        $("#share_edit_delete").click(function () {
            shareSetMode($("#share_editor").attr('data-user_login'), 0);
            // удалить шару из списка
            $("#shares option[value=" + $("#share_editor").attr('data-share_id') + "]").remove();
        });

        // создать шару / редактор
        $("#share_add_edit").click(function () {
            shareSetMode($("#share_new").val(), 2);
        });

        // создать шару / смотритель
        $("#share_add_see").click(function () {
            shareSetMode($("#share_new").val(), 3);
        });

        // -------------------------- Задачи ----------------------------------------
    }

    // ==========================  Отрисовка Списков ==========================
    function renderTodoList(todolist) {
        // заголовок текущего списка
        if (todolist[current_todoList_id]) {
            $('#list-panel h2').text(todolist[current_todoList_id].todolist_name);
        }

        var select = $('#todolists');
        $.each(todolist, function (key, val) {
                if (val.todolist_mode == 0) {
                    // удаленный список - удалить если есть
                    $('#list-panel option[data-todolist_id=' + key + ']').remove();
                }
                // такой список уже есть
                var opt = $('#list-panel option[data-todolist_id=' + key + ']');
                // и он устарел
                if (opt.length) {
                    if (opt.attr('data-todolist_updated') < val.todolist_updated) {
                        // обновить
                        if (val.todolist_mode != 0) {
                            opt.addClass('updated').attr('data-todolist_updated', val.todolist_updated);
                            opt.text(val.todolist_name);
                        } else {
                            opt.remove();
                        }
                    }
                } else {
                    // создаем новый
                    if (val.todolist_mode != 0) {
                        select.append(
                            $("<option/>", {value: key, text: val.todolist_name})
                                .attr('data-todolist_id', val.todolist_id)
                                .attr('data-todolist_mode', val.todolist_mode)
                                .attr('data-todolist_updated', val.todolist_updated)
                                .addClass('todolist_mode_' + val.todolist_mode)
                        );
                    }
                }
            }
        );
        selectResize(select);
        $('#list-panel').show();
    }


    // ==========================  Отрисовка Шар ==========================
    function renderShareList(todolist) {

        var select = $('#shares');
        if (todolist[current_todoList_id]) {
            $.each(todolist[current_todoList_id].user, function (key, val) {

                if (val.user_id != current_user.id) {
                    // такая шара уже есть
                    var opt = $('#shares option[data-share_id=' + key + ']');
                    // и она устарела
                    if (opt.length) {
                        if (opt.attr('data-share_updated') < val.share_updated) {
                            // обновить
                            if (val.share_mode != 0) {
                                opt.addClass('updated')
                                    .attr('data-share_updated', val.share_updated)
                                    .attr('data-share_mode', val.share_mode)
                                    .removeClass()
                                    .addClass('share_mode_' + val.share_mode);
                                opt.text(val.user_name);
                            } else {
                                opt.remove();
                            }
                        }
                    } else {
                        // создаем новый
                        if (val.share_mode != 0) {
                            select.append(
                                $("<option/>", {value: key, text: val.user_name})
                                    .attr('data-user_login', val.user_name)
                                    .attr('data-share_id', val.share_id)
                                    .attr('data-share_mode', val.share_mode)
                                    .attr('data-share_updated', val.share_updated)
                                    .addClass('share_mode_' + val.share_mode)
                            );
                        }
                    }
                }
            });

            selectResize(select);
            $('#share-panel').show();
        }
    }

    function selectResize(select) {
        var colOpts = select.find('option').length;
        if (colOpts) {
            select.attr('size', colOpts + 1);
            select.show().next().show();

        } else {
            select.hide().next().hide();
        }
    }

    /**
     * Запрос на сервер
     * @param param
     * @param success
     */
    function todoPost(param, success, error) {

       // log(param);
        $.ajax({
            type: 'POST',
            url: '',
            data: $.extend({route: 'post'}, param),
            success: success,
            error: error,
            dataType: 'json'
        });
    }

    function evtSourceOnMessage(data) {
        current_todoList_id = data.current_todoList_id;

        if (data.todolist) {
            // рисуем ошибку
            if (data.error) {
                errorlog(data.error)
                return;
            }
            // рисуем списки
            renderTodoList(data.todolist);

            // рисуем френдов
            renderShareList(data.todolist);
        }
    }


    function startSSE(data) {
        if (data && data.error) {
            errorlog(data.error)
        }
        log(' start SSE + current_todoList_id:' + current_todoList_id);
        stopSSE();

        evtSource = new EventSource("?route=post&action=sse&todolist_id=" + current_todoList_id);

        evtSource.onerror = function (e) {
            if (this.readyState == EventSource.CONNECTING) {
              //  console.log("Ошибка соединения, переподключение");
            } else {
               // console.log("Состояние ошибки: " + this.readyState);
            }
        };

        evtSource.onopen = function (e) {
            console.log("Открыто соединение");
        };

        evtSource.addEventListener("ping", function (e) {
            var obj = JSON.parse(e.data);
            log("ping at " + obj.time);
        }, false);

        evtSource.addEventListener("todo", function (e) {
             log(JSON.parse(e.data));
            evtSourceOnMessage(JSON.parse(e.data));
        }, false);

    }

    function stopSSE() {
        log('SSE stopped!');
        if (evtSource) {
            evtSource.close();
        }
    }

    function errorlog(error) {
        // очистить
        $('#error').text('');
        // показать
        for (var code in error) {
            $('#error').text(code + ': ' + error[code]);
        }

    }

    function log(val) {
        console.log(val);
    }

})(window);
