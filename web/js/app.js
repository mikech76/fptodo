(function (window) {
    'use strict';

    // Server-Side Event
    var evtSource = null;
    // авторизованный юзер
    var current_user = null;
    // текущий список
    var current_todoList_id = null;

    const SHARE_DELETE = 0;
    const SHARE_OWNER = 1;
    const SHARE_EDIT = 2;
    const SHARE_SEE = 3;

    const TASK_DELETE = 0;
    const TASK_OPEN = 1;
    const TASK_CLOSE = 2;

    const FILTER_ALL = 'All';
    const FILTER_ACTIVE = 'Active';
    const FILTER_COMPLETED = 'Completed';

    // ==========================  Запуск ==========================

    function initTodoListPanel () {

        $('#todolist_name').text('');
        $('#todolist_editor').hide();
        $('#todolists option').remove();
        $('#todolists').hide();
    }

    function initSharePanel () {
        $('#shares option').remove();
        $('#share-panel').hide();

    }

    function initTaskPanel (allClear) {
        $('#todo-list li').remove();
        $('#todoapp').hide().removeClass('disabled');

        allClear ? filterTask($('#todoapp .filters a.selected')) : filterTask($('#todoapp .filters a:first'));
    }

    initTodoListPanel();
    initSharePanel();
    initTaskPanel(true);

    // проверка авторизации
    todoPost({}, userAuth);

    // идентификация пользователя
    function userAuth (data) {

        if (data.user) {
            //авторизован
            errorlog();

            current_user = data.user;

            $('#user-panel h1 .user_name').html(current_user.login);
            $('#user-panel .destroy').show(); // кнопка удаления

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
            $('.todoapp').hide();

            stopSSE();
        }
    }


    /**
     * обработчики приложения
     */

    // ------------------------------ Юзер Блок -------------------------------
    // ============================  Вход ====================================
    $("#login-form form").submit(function (e) {
        e.preventDefault();

        // очистить все

        initTodoListPanel();
        initSharePanel();
        initTaskPanel();

        var form = $(e.target);
        var user_login = $('[name=user_login]').val();
        if (user_login.length < 3) {
            userAuth({error: {user_login_small: "Имя пользователя мало."}});
            return;
        }
        var user_password = $('[name=user_password]').val();

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
    $("#user-panel .destroy").click(function () {
        log('выход!')
        document.cookie = "user_id=; expires=" + (new Date(0)).toUTCString();
        todoPost({}, userAuth);
    });

    // ==========================  Форма Регистрации ==========================
    $("#register-form form").submit(function (e) {
        e.preventDefault();

        var form = $(e.target);
        var user_login = form.find('[name=user_login]').val();
        if (user_login.length < 3) {
            userAuth({error: {user_login_small: "Имя пользователя мало."}});
            return;
        }
        var user_password = form.find('[name=user_password]').val();
        var user_password2 = form.find('[name=user_password2]').val();

        if (user_password.length < 3 || user_password !== user_password2) {
            userAuth({error: {passwords_dont_coincide: "Пароли не совпадают"}});

            return false;
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
    $("#todolists").click(function () {
        var selected = $(this).find(':selected');
        if (current_todoList_id != selected.val()) {
            current_todoList_id = selected.val();

            selected.removeClass('updated');
            // очистить Шары
            initSharePanel();

            //   очистить таски
            initTaskPanel();

            startSSE();
        }
    });

    // =====================  Редактирование списка задач ======================

    // создать список
    // @test http://mysite/?route=post&action=todolist_create&todolist_name=На%20пикник
    $("#todolist_new").change(function () {
        var todolist_name = $("#todolist_new").val().trim();
        if (todolist_name.length) {
            todoPost({
                action: 'todolist_create',
                todolist_name: todolist_name
            }, function (data) {
                // проверить на ошибки data.error

                if (data && data.todolist) {
                    current_todoList_id = data.todolist.id;
                }
                startSSE();
            });
        } else {
            errorlog({error: 'Имя списка не может быть пустым!'});
        }
        $("#todolist_new").val('');
        initSharePanel();

        initTaskPanel();
    });

    // переименование
    // открывает редактор
    $("#list-panel b").dblclick(function () {
        $("#todolist_editor").val($("#todolist_name").hide().text()).show().focus();
    });
    // после изменения имени
    $("#todolist_editor").change(function () {
        var todolist_name_new = $(this).val().trim();
        // новое имя
        if (todolist_name_new.length) {
            // отправляем
            todoPost({
                action: 'todolist_update',
                todolist_id: current_todoList_id,
                todolist_name: todolist_name_new
            }, function (data) {
                //log(data);
                // проверить на ошибки data.error
                if (data && data.todolist) {
                    var opt = $('#list-panel option[data-todolist_id=' + data.todolist.id + ']');
                    opt.attr('data-todolist_updated', data.todolist.updated);
                    opt.text(data.todolist.name);
                }
                startSSE();
            });
            // в заголовок
            $("#todolist_name").text(todolist_name_new);
            $("#list-panel h1").addClass('set');
        } else {
            errorlog({error: 'Имя списка не может быть пустым!'});
        }

        $(this).focusout();
    }).focusout(function () {
        $("#todolist_editor").hide();
        $("#todolist_name").show();
    });


    // удалить -> у шары статус 0
    $("#list-panel .destroy").click(function () {
        var user_login = current_user.login;

        todoPost({
            action: 'todolist_share',
            share_user_login: user_login,
            share_todolist_id: current_todoList_id,
            share_mode: 0 // delete
        }, function (data) {
            // проверить на ошибки data.error

            startSSE();
        });
        // удалить список из селекта
        $("#todolists option[value=" + current_todoList_id + "]").remove();
    });

    // -------------------------- Шары ----------------------------------------

    $("#share-panel form").submit(function (e) {
        e.preventDefault();
    });

    // -------------------------- Шары ---------------------------------------

    // ===================  Выбор шары ==========================
    function shareControls () {

        var todoListMode = $('#todolists option:selected').attr('data-todolist_mode');
        if (todoListMode != 1) {
            // редактировать шары можно только у личногосписка
            return;
        }

        var selected = $('#shares :selected');

        $("#share_editor")
            .val(selected.text())
            .attr('data-user_login', selected.text())
            .attr('data-share_id', selected.val());

        $('#share-panel .share-control').show();

        switch (selected.attr('data-share_mode')) {
            case "1": // owner - только удалить/отписаться
                $("#share_edit_edit").hide();
                $("#share_edit_see").hide();
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
    }

    $("#shares").click(function () {
        shareControls();
    });

    // ===================  Сменить статус шары ==========================
    function shareSetMode (user_login, mode) {
        if (!user_login.length) {
            return;
        }

        todoPost({
            action: 'todolist_share',
            share_user_login: user_login,
            share_todolist_id: current_todoList_id,
            share_mode: mode
        }, function (data) {
            // проверить на ошибки data.error

            if (data && data.todolist) {

            }
            startSSE();
        });
        $("#share_editor, #share_new").val("");
        //  $("#share_edit_edit, #share_edit_see, #share_edit_delete").hide();
    }

    // переключить в режим редактирования
    $("#share_edit_edit").click(function () {
        shareSetMode($("#share_editor").attr('data-user_login'), SHARE_EDIT);
    });

    // переключить в режим чтения
    $("#share_edit_see").click(function () {
        shareSetMode($("#share_editor").attr('data-user_login'), SHARE_SEE);
    });

    // удалить шару
    $("#share_edit_delete").click(function () {
        shareSetMode($("#share_editor").attr('data-user_login'), SHARE_DELETE);
        // удалить шару из списка
        $("#shares option[value=" + $("#share_editor").attr('data-share_id') + "]").remove();
    });

    // создать шару / редактор
    $("#share_add_edit").click(function () {
        shareSetMode($("#share_new").val(), SHARE_EDIT);
    });

    // создать шару / смотритель
    $("#share_add_see").click(function () {
        shareSetMode($("#share_new").val(), SHARE_SEE);
    });


    // ------------------------------ Задачи ---------------------------------------

    // ==========================  Фильтры задач ==========================

    /**
     * Применяет фильтр и устанавливает счетчик
     * @param filter
     */
    function filterTask (filter) {

        // по умолчанию первый FILTER_ALL
        if (filter === undefined) {
            filter = $('#todoapp .filters a:first');
        }

        // все отключим
        $('#todoapp .filters a').removeClass('selected');

        switch (filter.text()) {

            case FILTER_ALL:
                $('#todo-list li').show();
                break;

            case FILTER_ACTIVE:
                $('#todo-list li:not(.completed)').show();
                $('#todo-list li.completed').hide();
                break;

            case FILTER_COMPLETED:
                $('#todo-list li.completed').show();
                $('#todo-list li:not(.completed)').hide();
                break;
        }
        // выбранный включим
        filter.addClass('selected');

        // счетчик активных
        $('#todoapp .footer .todo-count strong').text($('#todo-list li:not(.completed)').length);
        // исправляем все чекбоксы
    }

    // фильтры
    $('#todoapp .filters a').click(function () {
        filterTask($(this));
    });


    // ==========================  Создать задачу ==========================

    $("#todoapp .new-todo").change(function () {
        var task_name_new = $(this).val().trim();

        if (task_name_new.length) {
            todoPost({
                action: 'todotask_create',
                todotask_todolist_id: current_todoList_id,
                todotask_name: task_name_new
            }, function (data) {

                // проверить на ошибки data.error
                if (data && data.todotask) {
                    // обновляем  дату списка чтобы не светился
                    var opt = $('#list-panel option[data-todolist_id=' + data.todotask.todoListId + ']');
                    opt.attr('data-todolist_updated', data.todotask.updated);
                }
                startSSE();
            }, function () {
                errorlog({error: 'Задача не обновлена'});
            });
        } else {
            errorlog({error: 'Имя задачи не может быть пустым!'});
        }
        $("#todoapp .new-todo").val('');
    });

    // ==========================  Сменить статус задачи ==========================
    $("#todo-list").on("change", ".toggle", function () {
        var task = $(this).parents('li:first').attr('data-task_id');
        var status = $(this).prop("checked") ? TASK_CLOSE : TASK_OPEN;
        taskSetMode(task, status);
    });

    // ==========================  Сменить статус группы задач ==========================
    $("#todoapp .toggle-all").change(function () {
        // есть закрытые
        var status = $("#todo-list li:not([data-task_status=2])").length ? TASK_CLOSE : TASK_OPEN;

        taskSetMode(current_todoList_id, status, true);
    });

    // ============================  Удалить задачу ===============================
    $("#todo-list").on("click", ".destroy", function () {
        var task = $(this).parents('li:first');
        var status = TASK_DELETE; // удалить

        taskSetMode(task.attr('data-task_id'), status);
        task.remove();
    });

    // ===================  Удалить все закрытые задачи =======================
    $("#todoapp .clear-completed").click(function () {
        // есть закрытые

        if ($("#todoapp:not(.disabled) #todo-list li[data-task_status=2]").length) {
            taskSetMode(current_todoList_id, TASK_DELETE, true);
            $("#todo-list li[data-task_status=2]").remove();
        }
    });

    /**
     * Сменить статус одной задачи, или всех в списке
     * @param id - todotask_id или todolist_id
     * @param status
     * @param group - true если применить ко всему списку
     */
    function taskSetMode (id, status, group) {
        var params = {
            action: 'todotask_update',
            todotask_status: status
        };

        if (group) {
            params.todolist_id = id;
        } else {
            params.todotask_id = id;
        }

        todoPost(params, function (data) {

            // проверить на ошибки data.error
            if (data && data.todotask) {
                // обновляем  дату списка чтобы не светился
                var opt = $('#list-panel option[data-todolist_id=' + data.todotask.todoListId + ']');
                opt.attr('data-todolist_updated', data.todotask.updated);
            }
            startSSE();
        });

        filterTask($('#todoapp .filters a.selected'));
    }


    // ======================  Переименовать задачу  ==========================
    // открыть редактирование имени
    $("#todo-list").on("dblclick", "label", function () {
        var task = $(this).parents('li:first')
        task.find('.edit')
            .val(
                task.find('.view').hide().find('label').text())
            .show()
            .focus()
            .one('focusout', function () {
                $(this).hide().parent().find('.view').show().find('label').text($(this).val());
            })
            .one('change', function () {
                // новое имя
                var todotask_name_new = $(this).val().trim();

                if (todotask_name_new.length) {
                    // отправляем
                    todoPost({
                        action: 'todotask_update',
                        todotask_id: task.attr('data-task_id'),
                        todotask_name: todotask_name_new
                    }, function (data) {
                        //log(data);
                        // проверить на ошибки data.error
                        if (data && data.todotask) {
                            // обновляем  дату списка чтобы не светился
                            var opt = $('#list-panel option[data-todolist_id=' + data.todotask.todoListId + ']');
                            opt.attr('data-todolist_updated', data.todotask.updated);
                        }
                        startSSE();
                    });


                } else {
                    errorlog({error: 'Имя задачи не может быть пустым!'});
                }
                $(this).focusout();
            });

    });

    // ==========================  Отрисовка Списков ==========================
    function renderTodoList (todolist) {
        // заголовок текущего списка
        if (current_todoList_id) {
            if (todolist[current_todoList_id]) {
                $('#todolist_name').text(todolist[current_todoList_id].todolist_name);
                $("#list-panel").addClass('set'); // кнопка удаления
            }
        } else {
            $("#list-panel").removeClass('set'); // кнопка удаления
        }

        var select = $('#todolists');
        $.each(todolist, function (key, val) {
                // такой список уже есть
                var opt = $('#list-panel option[data-todolist_id=' + key + ']');
                // и он устарел
                if (opt.length) {
                    if (opt.attr('data-todolist_updated') < val.todolist_updated) {
                        // обновить
                        if (val.todolist_mode != '0') {
                            opt.removeClass();
                            opt.addClass('todolist_mode_' + val.todolist_mode);
                            opt.addClass('updated').attr('data-todolist_updated', val.todolist_updated);
                            opt.attr('data-todolist_mode', val.todolist_mode);
                            opt.text(val.todolist_name);
                        } else {
                            opt.remove();
                        }
                    }
                } else {
                    // создаем новый
                    if (val.todolist_mode != '0') {
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
        $('#list-panel option[data-todolist_id=' + current_todoList_id + ']').prop('selected', true);
        selectResize(select);
    }


    // ==========================  Отрисовка Шар ==========================
    function renderShareList (todolist) {

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
                            if (val.share_mode != SHARE_DELETE) {
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
                        if (val.share_mode != SHARE_DELETE) {
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
        }

        $('#share-panel').show();

        var colOpts = selectResize(select);
        if (colOpts) {
            shareControls();
            $('#todoapp').show();
        } else {
            $('#share-panel .share-control').hide();
        }


    }

    function selectResize (select) {
        var colOpts = select.find('option').length;

        if (colOpts) {
            select.attr('size', colOpts + 1);
            select.show();
            select.find('option:first').attr('selected', true);

        } else {
            select.hide();
        }
        return colOpts;
    }

    // ===============================  Отрисовка задач  =======================================

    function renderTaskList (todolist) {

        var todoTasks = $('#todo-list');

        $.each(todolist[current_todoList_id].todotask, function (key, val) {

            var task = todoTasks.find('li[data-task_id=' + key + ']');
            // такая задача  уже есть
            if (task.length) {
                // и она устарела
                if (task.attr('data-task_updated') < val.task_updated) {
                    // обновить
                    log(val);
                    if (val.status != TASK_DELETE) {
                        if (val.status == TASK_OPEN) {
                            task.removeClass('completed');
                            task.find('.toggle').prop("checked", false);
                        } else {
                            // TASK_CLOSE
                            task.addClass('completed');
                            task.find('.toggle').prop("checked", true);
                        }
                        task.find('label').text(val.task_name);
                        task.attr('data-task_status', val.status);
                    } else {
                        task.remove();
                    }
                }
            } else {
                // создаем новый
                if (val.status != TASK_DELETE) {
                    task = $($("#taskLiTmp").html());

                    task.find('label').text(val.task_name);
                    if (val.status == TASK_CLOSE) {
                        task.addClass('completed');
                        task.find('.toggle').prop("checked", true);
                    }
                    task.attr('data-task_status', val.status)
                        .attr('data-task_id', val.task_id)
                        .attr('data-task_updated', val.task_updated);

                    todoTasks.append(task);
                }
            }

        });
        $('#todoapp').show();
        if (todolist[current_todoList_id].todolist_mode == SHARE_SEE) {
            // заблокировать форму
            $('#todoapp').addClass('disabled');
            // элементы
            $('#todoapp input').prop('disabled', true);
        } else {
            // разблокировать форму
            $('#todoapp').removeClass('disabled');
            // элементы
            $('#todoapp input').prop('disabled', false);
        }
        filterTask($('#todoapp .filters a.selected'));
    }


    // =================================== Utils ===============================================
    /**
     * Запрос на сервер
     * @param param
     * @param success
     * @param error
     */
    function todoPost (param, success, error) {

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

    /**
     * Обработка принятых данных
     * @param data
     */
    function evtSourceOnMessage (data) {
        //порция данных
        log(data);

        log('set current_todoList_id = ' + parseInt(data.current_todoList_id));

        // сменился ли текущий список
        var reconn = current_todoList_id != data.current_todoList_id;
        // запомним текущий список
        current_todoList_id = data.current_todoList_id;

        // есть ли новые данные
        if (data.todolist) {
            // рисуем ошибку
            if (data.error) {
                errorlog(data.error);
                return;
            }

            // рисуем списки
            renderTodoList(data.todolist);

            // рисуем шары
            renderShareList(data.todolist);

            // рисуем таски
            if (data.todolist[current_todoList_id]) {
                renderTaskList(data.todolist);
            }
        }
        // переоткрываем поток с параметром текущего списка
        if (reconn) {
            startSSE();
        }
    }

    /**
     * Инициализация SSE stream
     * @param data
     */
    function startSSE (data) {
        // запуск sse с выводом ошибки
        if (data && data.error) {
            errorlog(data.error)
        }

        // закрыть поток если открыт
        stopSSE();

        var route_url = "?route=post&action=sse&todolist_id=" + current_todoList_id
        log('Start event-stream: ' + route_url);

        // запускаем sse
        evtSource = new EventSource(route_url);

        // событие ошибки
        evtSource.onerror = function (e) {
            if (this.readyState == EventSource.CONNECTING) {
                console.log("Ошибка соединения, переподключение");
            } else {
                console.log("Состояние ошибки: " + this.readyState);
            }
        };

        // событие  коннект
        evtSource.onopen = function (e) {
            console.log("Открыто соединение");
        };

        // событие ping для теста
        evtSource.addEventListener("ping", function (e) {
            var obj = JSON.parse(e.data);
            log("ping at " + obj.time);
        }, false);

        // событие - пришла порция данных
        evtSource.addEventListener("todo", function (e) {
            evtSourceOnMessage(JSON.parse(e.data));
        }, false);

    }

    // закрыть SSE
    function stopSSE () {
        if (evtSource) {
            evtSource.close();
        }
    }

    // вывод текста ошибки
    function errorlog (error) {
        // очистить
        $('#error').text('');
        // показать
        for (var code in error) {
            $('#error').text(code + ': ' + error[code]);
        }
    }

    // лог
    function log (val) {
        console.log(val);
    }


})(window);
