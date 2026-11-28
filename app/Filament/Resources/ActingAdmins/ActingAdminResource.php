<?php

namespace App\Filament\Resources\ActingAdmins;

use App\Enums\ActingAdminLevel;
use App\Enums\UserType;
use App\Filament\Resources\ActingAdmins\Pages\CreateActingAdmin;
use App\Filament\Resources\ActingAdmins\Pages\EditActingAdmin;
use App\Filament\Resources\ActingAdmins\Pages\ListActingAdmins;
use App\Filament\Resources\ActingAdmins\Schemas\ActingAdminForm;
use App\Filament\Resources\ActingAdmins\Tables\ActingAdminsTable;
use App\Models\ActingAdmin;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * An acting assignment hands out admin powers, so each action needs its own
 * `*_acting_admins` permission. Only the super-admin may hand out (or change)
 * the Acting Super Admin level, and nobody else may touch their own assignment.
 *
 * Filament authorizes actions and pages through the get*AuthorizationResponse()
 * methods, so the rules live there rather than in canCreate()/canEdit().
 */
class ActingAdminResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = ActingAdmin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'HR & Staff Management';

    protected static ?int $navigationSort = 3;

    public static function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole(UserType::SuperAdmin->roleName());
    }

    /**
     * The levels the current user may assign: the super-admin may give either,
     * everyone else only the plain Acting Admin level.
     *
     * @return array<string, string>
     */
    public static function assignableLevelOptions(): array
    {
        if (static::isSuperAdmin()) {
            return ActingAdminLevel::options();
        }

        return [ActingAdminLevel::Admin->value => ActingAdminLevel::Admin->getLabel()];
    }

    public static function canCreate(): bool
    {
        return static::getCreateAuthorizationResponse()->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::getEditAuthorizationResponse($record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return self::permissionResponse('create_acting_admins');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return self::assignmentResponse($record, 'edit_acting_admins');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return self::assignmentResponse($record, 'delete_acting_admins');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return self::permissionResponse('delete_acting_admins');
    }

    private static function assignmentResponse(Model $record, string $permission): Response
    {
        if (static::isSuperAdmin()) {
            return Response::allow();
        }

        if ($record->level === ActingAdminLevel::SuperAdmin) {
            return Response::deny('Acting Super Admin শুধু Super Admin বদলাতে পারবে।');
        }

        if ($record->user_id === auth()->id()) {
            return Response::deny('নিজের Acting Admin দায়িত্ব নিজে বদলানো যাবে না।');
        }

        return self::permissionResponse($permission);
    }

    private static function permissionResponse(string $permission): Response
    {
        return auth()->user()?->can($permission)
            ? Response::allow()
            : Response::deny('এই কাজের অনুমতি আপনার নেই।');
    }

    public static function form(Schema $schema): Schema
    {
        return ActingAdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActingAdminsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActingAdmins::route('/'),
            'create' => CreateActingAdmin::route('/create'),
            'edit' => EditActingAdmin::route('/{record}/edit'),
        ];
    }
}
