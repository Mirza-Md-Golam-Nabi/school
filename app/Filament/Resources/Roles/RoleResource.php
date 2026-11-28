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

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return self::protectedRoleResponse($record, 'edit');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record->name === UserType::SuperAdmin->roleName()) {
            return Response::deny('Super Admin role মুছে ফেলা যাবে না।');
        }

        return self::protectedRoleResponse($record, 'delete');
    }

    private static function protectedRoleResponse(Model $record, string $action): Response
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

        return static::entityPermissionResponse($action);
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
