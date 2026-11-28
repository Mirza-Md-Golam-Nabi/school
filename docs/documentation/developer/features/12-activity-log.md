# ১২. Activity Log — Developer Reference

ব্যবহারকারী নির্দেশিকা: [../../user-guide/features/12-activity-log.md](../../user-guide/features/12-activity-log.md)

## সূচিপত্র

- [১২.১ কোড ফাইল](#১২১-কোড-ফাইল)
- [১২.২ কীভাবে log হয়](#১২২-কীভাবে-log-হয়)
- [১২.৩ Label cache](#১২৩-label-cache)
- [১২.৪ Auth event](#১২৪-auth-event)
- [১২.৫ Retention](#১২৫-retention)
- [১২.৬ নতুন model-এ logging যোগ করা](#১২৬-নতুন-model-এ-logging-যোগ-করা)
- [১২.৭ টেস্ট](#১২৭-টেস্ট)

---

## ১২.১ কোড ফাইল

| অংশ | ফাইল |
|---|---|
| Resource | `app/Filament/Resources/ActivityLogs/` — List (Module ও Event filter), View page (সাধারণ তথ্য, Old Value, New Value, অতিরিক্ত তথ্য — `KeyValueEntry`) |
| Model | `app/Models/ActivityLog.php` (spatie `Activity` extend) |
| Enum | `ActivityLogEvent` — created, updated, deleted, restored, login, logout, failed_login, calculated, generated |
| Traits | `LogsRelationLabels` (foreign key-এর বদলে মানুষের পড়ার মতো label) |
| Support | `ActivityLogLabelCache` (scoped singleton) |
| Listeners | `LogSuccessfulLogin`, `LogSuccessfulLogout`, `LogFailedLogin` |
| Config | `config/activitylog.php` |
| Permission | `ActivityLogPermission::VIEW_ACTIVITY_LOG` — resource `HasEntityPermissions` (table `activity_log` → `view_activity_log`) |

## ১২.২ কীভাবে log হয়

- বেশিরভাগ model spatie `LogsActivity` + `getActivitylogOptions()` — dirty attribute, `log_name` = module
- `tapActivity` / `activityLogDescription()` — মানুষের পড়ার মতো description (যেমন leave application, student result)
- Bulk বা কয়েক-ধাপের কাজে model logging বন্ধ করে একটা custom `activity('module')` (যেমন `CreateExamTypeAction`, merit ranking calculate, invoice / admit card / marksheet generation)
- Role permission পরিবর্তন: `EditRole::logPermissionChanges()`

## ১২.৩ Label cache

`ActivityLogLabelCache` — এক request-এ একই foreign key-এর label একবার query। `ActivityLogLabelCache::SOURCE_MODELS`-এর `updated` / `deleted` / `restored` event-এ flush (`AppServiceProvider`), যাতে rename-এর পর পুরনো label না থাকে।

## ১২.৪ Auth event

`app/Listeners/*` auto-discovered। `AppServiceProvider`-এ আবার register করবেন না — দুবার log হবে (`AuthenticationActivityLogTest`)।

## ১২.৫ Retention

`delete_records_older_than_days = 730`; `activitylog:clean` প্রতি রবিবার ০১:০০।

## ১২.৬ নতুন model-এ logging যোগ করা

1. `use LogsActivity` (+ দরকার হলে `LogsRelationLabels`)
2. `getActivitylogOptions()` — `logOnly` / `logOnlyDirty`, `useLogName('module')`, `dontSubmitEmptyLogs()`
3. Foreign key label দরকার হলে `activityLogRelationLabels()`
4. Model যদি label source হয়, `ActivityLogLabelCache::SOURCE_MODELS`-এ যোগ
5. `ActivityLog*Test` প্যাটার্নে টেস্ট

## ১২.৭ টেস্ট

`ActivityLogResourceTest`, `ActivityLogModelLoggingTest`, `AuthenticationActivityLogTest` এবং প্রতিটা module-এর `ActivityLog*Test` (প্রায় ৩০টা)।
