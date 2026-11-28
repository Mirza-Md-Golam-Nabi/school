# ৫. Communication ও Push Notification — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/05-communication.md](../../user-guide/features/05-communication.md)

## সূচিপত্র

- [৫.১ কোড ফাইল](#৫১-কোড-ফাইল)
- [৫.২ Database টেবিল](#৫২-database-টেবিল)
- [৫.৩ Permission](#৫৩-permission)
- [৫.৪ Notice targeting](#৫৪-notice-targeting)
- [৫.৫ Publishing ও scheduling](#৫৫-publishing-ও-scheduling)
- [৫.৬ Notice SMS](#৫৬-notice-sms)
- [৫.৭ Read tracking](#৫৭-read-tracking)
- [৫.৮ Web push delivery ও retry](#৫৮-web-push-delivery-ও-retry)
- [৫.৯ Notification class তালিকা](#৫৯-notification-class-তালিকা)
- [৫.১০ টেস্ট](#৫১০-টেস্ট)
- [৫.১১ জানা সমস্যা ও সতর্কতা](#৫১১-জানা-সমস্যা-ও-সতর্কতা)

---

## ৫.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resource | `app/Filament/Resources/Notices/` — `Schemas/NoticeForm`, `Schemas/NoticeInfolist`, `Tables/NoticesTable`, `Pages/{List,Create,Edit,View}Notice` |
| Panel pages | `app/Filament/Teacher/Pages/Notices.php`, `app/Filament/Student/Pages/Notices.php`, concerns `Teacher/Concerns/InteractsWithNotices`, `Student/Concerns/InteractsWithNotices` |
| Widgets | `Teacher/Widgets/TeacherNoticesOverview`, `Student/Widgets/StudentNoticesOverview` |
| Job | `app/Jobs/PublishNoticeJob.php` |
| SMS | `app/Actions/Sms/ResolveNoticeSmsRecipientsAction`, `SendNoticeSmsAction` |
| Push | `PushSubscriptionController`, `PushNotificationDeliveryController`, `Requests/StorePushSubscriptionRequest`, `DestroyPushSubscriptionRequest`, `AcknowledgePushNotificationRequest`, view `filament/shared/webpush-subscribe.blade.php`, `Notifications/Channels/*`, `Notifications/Concerns/*` |
| Commands | `ResendUnacknowledgedPushNotifications`, `PrunePushNotificationDeliveries` |
| Config | `config/push_notifications.php`, `config/webpush.php` |
| Models | `Notice`, `NoticeTarget`, `NoticeRead`, `PushNotificationDelivery` |
| Enums | `NoticeTargetType`, `NotificationStatus`, `NotificationType` |

## ৫.২ Database টেবিল

| টেবিল | নোট |
|---|---|
| `notices` | `title`, `body` (rich HTML), `target_type`, `published_at`, `send_sms`, `created_by` |
| `notice_targets` | polymorphic `targetable` — class বা user (individual) |
| `notice_reads` | `notice_id`, `user_id`, read time |
| `push_subscriptions` | webpush package-এর টেবিল |
| `push_notification_deliveries` | প্রতি push-এর delivery, acknowledge, attempt সংখ্যা |
| `notifications` | Laravel database notification (bell icon) |

## ৫.৩ Permission

`CommunicationPermission`: `view_notices`, `create_notices`, `edit_notices`, `delete_notices` — `NoticeResource` `HasEntityPermissions` ব্যবহার করে।

## ৫.৪ Notice targeting

`NoticeTargetType`: `all`, `students`, `teachers`, `class`, `individual_student`, `individual_teacher`। `requiresTargets()` শেষ তিনটার জন্য `target_ids` চায়। `individual_student`-এ `class_filter_id` (UI-only) দিয়ে student list ছোট হয়।

Visibility (`Notice` model scope):

- Student: `all`, `students`, `class` (student-এর `current_class_id` targets-এ), `individual_student` (user target)
- Teacher: `all`, `teachers`, `individual_teacher`

## ৫.৫ Publishing ও scheduling

- `published_at` খালি → এখনই; ভবিষ্যৎ (`minDate(now())`) → `PublishNoticeJob::dispatch($id)->delay(...)`
- Create ও Edit দুই জায়গা থেকেই dispatch; job চলার সময় notice-এর `published_at` job-এর সময়ের সাথে না মিললে (reschedule বা clear হয়েছে) stale job কিছু করে না — নতুন job আলাদা queue-তে আছে
- Publish-এ `NoticeCreatedNotification` (database + webpush) এবং `send_sms` হলে SMS

## ৫.৬ Notice SMS

- `send_sms` toggle শুধু `SmsSetting::current()->isEventEnabled(SmsEvent::Notice)` হলে visible
- Helper text-এ live estimate: recipients, parts, cost, balance
- `ResolveNoticeSmsRecipientsAction`: student → guardian phone (fallback নিজের), teacher/staff → নিজের `users.phone`; `all` = students + active teachers + active staff
- `QueueSmsBatchAction` — de-dup (একই নম্বর + একই লেখা একবার), all-or-nothing

## ৫.৭ Read tracking

`InteractsWithNotices` — notice খোলা হলে `NoticeRead` তৈরি। `reads_count` table ও infolist-এ। Dashboard widget-এ unread সংখ্যা।

## ৫.৮ Web push delivery ও retry

- Subscribe: `POST /push-subscriptions` (Form Request `authorize()` → `auth()->check()`, নইলে 403), unsubscribe `DELETE`
- প্রতিটা push-এর `PushNotificationDelivery` রেকর্ড; service worker পেলে `POST /push-notification-deliveries/acknowledge` (`throttle:60,1`)
- `ResendUnacknowledgedPushNotifications` — cron `*/{retry_interval_minutes}` (ডিফল্ট ১০), `withoutOverlapping`; `scopeDueForRetry`; `max_attempts` (ডিফল্ট ৫)
- `PrunePushNotificationDeliveries` — দৈনিক ০২:০০, ১ সপ্তাহের পুরনো মোছে
- HTTPS ছাড়া push / PWA কাজ করে না

## ৫.৯ Notification class তালিকা

| Class | প্রাপক | ট্রিগার |
|---|---|---|
| `NoticeCreatedNotification` | notice target | publish |
| `StudentAttendanceMarkedNotification` | student | manual attendance নতুন / বদল |
| `StudentDevicePunchNotification` | student | device entry / প্রথম exit (আজ) |
| `StudentFeeInvoiceGeneratedNotification` | student | invoice তৈরি |
| `StudentFeeInvoiceUpdatedNotification` | student | invoice update |
| `FeePaymentReceivedNotification` | student | payment (payment মুছলে notification-ও মোছে) |
| `ExamMarksNotification` | student | Send Marks |
| `ExamResultPublishedNotification` | student | Send Result / publish |
| `SalaryInvoiceGeneratedNotification` | teacher / staff | salary invoice |
| `SalaryPaymentReceivedNotification` | teacher / staff | salary payment |
| `QueuedVerifyEmail` | user | email verification |

Filament database notification (class ছাড়া, `Notification::make()->sendToDatabase()`): backdated attendance, wrong class teacher, attendance modification batch।

## ৫.১০ টেস্ট

`NoticeCreatedNotificationTest`, `ActivityLogNoticeTest`, `StudentNoticesTest`, `TeacherNoticesTest`, `PushSubscriptionTest`, `PushNotificationDeliveryTest`, `WebPushRenderHookTest`, `SmsEventNotificationsTest`।

## ৫.১১ জানা সমস্যা ও সতর্কতা

1. Staff-এর জন্য notice target type নেই, আর staff-এর panel-এ notice দেখার পেজও নেই — অথচ `all` target-এ SMS staff-দের কাছেও যায়।
2. Leave application approve / reject-এ আবেদনকারীকে কোনো notification যায় না।
3. প্রকাশিত notice edit করলে নতুন করে push বা SMS যায় না (শুধু schedule বদলালে নতুন job)।
