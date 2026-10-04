<?php

namespace App\Filament\Resources\AttendanceDevices;

use App\Enums\Permissions\AttendancePermission;
use App\Filament\Concerns\HasAttendancePagePermission;
use App\Filament\Resources\AttendanceDevices\Pages\CreateAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\EditAttendanceDevice;
use App\Filament\Resources\AttendanceDevices\Pages\ListAttendanceDevices;
use App\Filament\Resources\AttendanceDevices\RelationManagers\DeviceUsersRelationManager;
use App\Filament\Resources\AttendanceDevices\Schemas\AttendanceDeviceForm;
use App\Filament\Resources\AttendanceDevices\Tables\AttendanceDevicesTable;
use App\Models\AttendanceDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceDeviceResource extends Resource
{
    use HasAttendancePagePermission;

    protected static ?string $model = AttendanceDevice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function attendancePermission(): AttendancePermission
    {
        return AttendancePermission::MANAGE_ATTENDANCE_DEVICES;
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceDeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceDevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeviceUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceDevices::route('/'),
            'create' => CreateAttendanceDevice::route('/create'),
            'edit' => EditAttendanceDevice::route('/{record}/edit'),
        ];
    }
}
