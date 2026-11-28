<?php

namespace App\Filament\Resources\LeaveApplications;

use App\Enums\Permissions\HrPermission;
use App\Enums\UserType;
use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\EditLeaveApplication;
use App\Filament\Resources\LeaveApplications\Pages\ListLeaveApplications;
use App\Filament\Resources\LeaveApplications\Schemas\LeaveApplicationForm;
use App\Filament\Resources\LeaveApplications\Tables\LeaveApplicationsTable;
use App\Models\LeaveApplication;
use App\Traits\Permissions\HasEntityPermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LeaveApplicationResource extends Resource
{
    use HasEntityPermissions;

    protected static ?string $model = LeaveApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'HR & Staff Management';

    protected static ?int $navigationSort = 2;

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! $record->isEditable()) {
            return Response::deny('অনুমোদিত ছুটির আবেদন আর বদলানো যায় না।');
        }

        return static::entityPermissionResponse('edit');
    }

    /**
     * Approve / reject need their own permission, and nobody but the
     * super-admin may decide on their own leave application.
     */
    public static function canDecide(LeaveApplication $record, HrPermission $permission): bool
    {
        $user = auth()->user();

        if (! $record->isPending() || ! $user?->can($permission->value)) {
            return false;
        }

        if ($user->hasRole(UserType::SuperAdmin->roleName())) {
            return true;
        }

        return $record->applicant?->user_id !== $user->id;
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeaveApplicationsTable::configure($table);
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
            'index' => ListLeaveApplications::route('/'),
            'create' => CreateLeaveApplication::route('/create'),
            'edit' => EditLeaveApplication::route('/{record}/edit'),
        ];
    }
}
