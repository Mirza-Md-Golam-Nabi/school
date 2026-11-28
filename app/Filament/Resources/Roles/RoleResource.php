<?php

namespace App\Filament\Resources\Roles;

use App\Enums\UserType;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Roles decide every permission in the system, so each action needs its own
 * `*_roles` permission, and only the super-admin may change the super-admin
 * role or a role they hold themselves (no self-escalation).
 *
 * Filament authorizes actions and pages through the get*AuthorizationResponse()
 * methods, so the rules live there rather than in canCreate()/canEdit().
 */
class RoleResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

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
        return self::permissionResponse('create_roles');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return self::protectedRoleResponse($record, 'edit_roles');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record->name === UserType::SuperAdmin->roleName()) {
            return Response::deny('Super Admin role মুছে ফেলা যাবে না।');
        }

        return self::protectedRoleResponse($record, 'delete_roles');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return self::permissionResponse('delete_roles');
    }

    private static function protectedRoleResponse(Model $record, string $permission): Response
    {
        $user = auth()->user();

        if ($user?->hasRole(UserType::SuperAdmin->roleName())) {
            return Response::allow();
        }

        if ($record->name === UserType::SuperAdmin->roleName()) {
            return Response::deny('Super Admin role শুধু Super Admin বদলাতে পারবে।');
        }

        if ($user?->hasRole($record->name)) {
            return Response::deny('নিজের role নিজে বদলানো যাবে না।');
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
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
