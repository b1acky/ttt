# Тестовое задание Karma8 "PHP Developer"

## Подготовка

Код проверялся на версии PHP 8.2, необходимые пакеты:

```
php8.2-cli php8.2-mysqli
```

* Создать базу данных MySQL `karma8`
* Создать юзера `karma8` с паролем `karma8` или поменять юзера и пароль в `src/db.php`
* Запустить `php bin/init.php` - будут запущены миграции и сидер данных, в очередь отправки нотификаций добавятся запланированные нотификации
* Добавить в крон нужное кол-во воркеров очереди
```
* * * * * /usr/bin/php8.2 /path/to/karma8/bin/daemon/queue_worker.php
* * * * * /usr/bin/php8.2 /path/to/karma8/bin/deamon/queue_worker.php
* * * * * /usr/bin/php8.2 /path/to/karma8/bin/daemon/queue_worker.php
```
* (Опционально) Добавить в крон монитор очереди
```
* * * * * /usr/bin/php8.2 /path/to/karma8/bin/daemon/queue_monitor.php
```

`bin/init.php` следует запустить только один раз при первоначальной настройке системы, или в случае если надо перезапустить всю базу данных с другими параметрами.

Для вывода отладочной информации используется syslog.

## Изменения в таблицах
* Добавлено поле `id` в таблице `users` (авто-инкремент)
* Создана таблица отложенных нотификаций `queue`

## Описание работы
### Принятые допущения
* Таблица юзеров считается статической - данные в ней не изменяются извне (не добавляются новые и не редактируются имеющиеся юзеры)
* Нет "хорошей" обработки ошибок очереди - считается, что внешние функции `check_email` и `send_email` *всегда* возвращают корректный результат. Обработчик очереди лишь логирует ошибки.
* Считаем, что деньги важнее быстроты системы - поэтому не делаем отдельную очередь проверки почт заранее. 

Более подробно эти допущения описаны в разделе ["Размышления и ИМХО"](#Размышления-и-ИМХО)

### Логика
Для каждого подписчика мы можем вычислить даты, когда ему нужно отправить нотификации (`notifier_get_notifications`). Узнав эти даты, мы создаем отложенные нотификации - кладём информацию в очередь.

Очередь разгребается воркерами (`bin/daemon/queue_worker.php`), их можно запускать параллельно. Воркер выбирает нотификации из очереди, которые пора отправить, и запускает логику проверки юзера (если требуется) и отправляет письма (`notifier_populate_queue`).

Для моделирования реальной ситуации используется сидер данных `database/seed.php`. Он учитывает вероятности из условий задачи (80% юзеров без подписки, 15% юзеров с подтвержденной почтой), также моделирует различные даты окончания подписки. Для моделирования принято, что подписка заканчивается в случайное время от "сейчас" до двух недель в будущем.

Эмпирическим путём установлено кол-во воркеров, при котором размер очереди не растёт со временем - 40.

### Полезные команды для syslog

```
# Мониторинг очереди отправки
grep karma8 /var/log/syslog | grep 'notifications to send'

# Проверка, что письмо отправлено за нужное кол-во дней
grep karma8 /var/log/syslog | grep 'time to subscription'

# Ошибки
grep karma8 /var/log/syslog | grep -P 'error|exception'
```

## Размышления и ИМХО

### Про статическую таблицу

В целом, почти ничего не меняется в архитектурном плане, если таблица становится динамической. Всё, что нам надо - обновлять таблицу `queue` в зав-ти от событий (например, удалить из очереди юзера, если ему обнулили подписку, или обновить таймер нотификации если юзеру продлили подписку и т.д.). Метод `notifier_get_notifications` - хорошее место для такой логики (с учетом текущей архитектуры кода, разумеется).

Исходя из опыта, скорее всего следует добавить колонку `last_notify_at` в `users`. С большой вероятностью логика отправки уведомлений будет меняться в будущем, поэтому нам нужно будет хранилище, в котором можно будет проверять отправленные нотификации, чтобы не спамить юзеров слишком часто. Как альтернатива - таблица со всеми отправленными уведомлениями за N дней.

На что может ещё повлиять "динамичность" таблицы - на транзакции. В текущем решении они не просто опущены, они в буквальном смысле не нужны. Воркеры используют оптимистичные локи для захвата элементов очереди, а исходя из текущей бизнес-логики мы можем быть уверены, что два разных воркера не будут обрабатывать нотификацию одного и того же юзера, следовательно нам не надо блокировать строку юзера в транзакции, чтобы избежать проблем с перезаписью или двойным запросом проверки почты.

### Про обработку ошибок

Самое простое, что приходит в голову - вставлять обратно таски из очереди, которые в процессе обработки столкнулись с ошибкой. Например, увеличивать значение поля `notify_ts` на несколько минут. Или ввести поле с кол-вом перезапусков таска. Всё это сильно выходит за рамки текущей задачи, т.к. требуется наличие "реальных" ошибок, например, в запросах `send_email` / `check_email`. Хорошей практикой можно считать таблицу сфейленных тасков, на которую нужно настроить мониторинг.

### Деньги важнее быстроты

Наверно, самое сложное допущения для принятия в голове. Исходим из описания задачи, что мы используем платный сервис проверки почт, следовательно, на какой-то % нам важно эффективно его использовать. Сам % можно вытащить только из разговоров с бизнесом и аналитикой.

В текущем решении этот "%" принят как 100% - проверяем почту только тогда, когда это надо. Это непосредственно влияет на среднюю продолжительность обработки таска в очереди (грубо, 30 секунд на проверку + 5 секунд на отправку в "средне-худшем" случае если у каждого юзера надо проверять почту).

Какие могут быть варианты в зав-ти от этого "%": например, если наша система упёрлась в определенную пропускную способность и мы больше не можем наращивать кол-во воркеров, то мы можем сделать предварительную оптимизацию процесса. Выбираем юзеров с подпиской, у кого она заканчивается не раньше чем N дней назад (для текущих условий, например, неделя), фильтруем тех, кому надо проверить почту, создаём под эту задачку дополнительную очередь. В итоге, к моменту отправки мы будем знать, каким юзерам письма точно дойдут. Это должно снизить среднее время обработки таска в основной очереди отправки, но за счет увеличения стоимости обработки.

Понятие "цена обработки" зависит от других возможных бизнес-процессов, например, если мы сможем выделить какие-то другие критерии юзеров, по которым поймем, что им не надо отправлять уведомления. Но это опять-таки сильно выходит за рамки условий задачи.