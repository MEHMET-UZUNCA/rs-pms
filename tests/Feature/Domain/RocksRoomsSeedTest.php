<?php

namespace Tests\Feature\Domain;

use Illuminate\Support\Facades\DB;

/** Fidelio Room Types / Rooms / Room Features / Room Sections / User Groups yüklemesi. */
class RocksRoomsSeedTest extends DomainTestCase
{
    public function test_rooms_types_features_and_blocks_follow_fidelio(): void
    {
        $this->assertSame(10, DB::table('pms_room_types')->where('property_id', $this->property)->count());
        $this->assertSame(155, DB::table('pms_rooms')->where('property_id', $this->property)->where('is_active', true)->count());
        $this->assertSame(0, (int) DB::table('pms_rooms')->whereIn('number', ['1310', '1311', '8885', '8888'])->where('is_active', true)->count());   // süresi bitmiş

        $room = fn (string $no) => DB::table('pms_rooms as r')->join('pms_room_types as t', 't.id', '=', 'r.room_type_id')
            ->join('pms_floors as f', 'f.id', '=', 'r.floor_id')->join('pms_buildings as b', 'b.id', '=', 'r.building_id')
            ->where('r.number', $no)->first(['t.code as type', 'f.code as floor', 'b.code as block', 'r.max_occupancy', 'r.is_accessible', 'r.id']);
        $this->assertSame(['YMT', '1', 'A', 3], [$room('1101')->type, $room('1101')->floor, $room('1101')->block, (int) $room('1101')->max_occupancy]);
        $this->assertSame(['ESU', '6', 'B', 3], [$room('2410')->type, $room('2410')->floor, $room('2410')->block, (int) $room('2410')->max_occupancy]);
        $this->assertTrue((bool) $room('1131')->is_accessible);   // "Engelli"

        $features = fn (string $no) => DB::table('pms_room_feature_room as x')->join('pms_room_features as f', 'f.id', '=', 'x.room_feature_id')
            ->where('x.room_id', $room($no)->id)->orderBy('f.code')->pluck('f.code')->all();
        $this->assertSame(['FB', 'SV'], $features('1114'));             // Deluxe,Deniz,French
        $this->assertSame(['CN', 'SV', 'TB'], $features('2207'));       // Superior,Deniz, Conn Twin
        $this->assertSame(['MV', 'NS', 'TB'], $features('2401'));       // Superior,Dağ,Twin,NonSmke
        $this->assertSame(['EN', 'FB', 'MV'], $features('1131'));

        $this->assertSame('Front Office Agent', DB::table('pms_roles')->where('code', 'FOA')->value('name'));
        $this->assertTrue(DB::table('pms_role_permissions as rp')->join('pms_roles as r', 'r.id', '=', 'rp.role_id')->join('pms_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.code', 'AUD')->where('p.code', 'night_audit.override')->exists());
        $this->assertFalse(DB::table('pms_role_permissions as rp')->join('pms_roles as r', 'r.id', '=', 'rp.role_id')->join('pms_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.code', 'FOA')->where('p.code', 'cashiering.reverse')->exists());
    }
}
