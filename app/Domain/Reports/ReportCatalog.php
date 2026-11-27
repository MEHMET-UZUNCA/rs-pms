<?php

namespace App\Domain\Reports;

/**
 * Rapor ekranının başlangıç seti (docs/10-raporlar.md §10.2). Her rapor: grup (ağaç ve yetki: reports.{grup}), süzgeçler,
 * ReportBuilder'daki yöntem. Başlık ve kolon adları dil dosyasında (pms.reports.r.{anahtar}, pms.reports.col.{kolon}).
 *
 * Süzgeçler: date (tek tarih), range (başlangıç–bitiş), room_type, user, unassigned (odası atanmamış), clean (yalnız temiz).
 */
class ReportCatalog
{
    public const GROUPS = ['front_office', 'housekeeping', 'occupancy', 'finance', 'rates'];

    /** @var array<string, array{group: string, filters: list<string>, method: string, landscape?: bool}> */
    public const REPORTS = [
        'arrivals' => ['group' => 'front_office', 'filters' => ['date', 'room_type', 'unassigned'], 'method' => 'arrivals', 'landscape' => true],
        'departures' => ['group' => 'front_office', 'filters' => ['date'], 'method' => 'departures'],
        'in_house' => ['group' => 'front_office', 'filters' => ['room_type'], 'method' => 'inHouse', 'landscape' => true],
        'cancellations' => ['group' => 'front_office', 'filters' => ['range'], 'method' => 'cancellations'],
        'nationality' => ['group' => 'front_office', 'filters' => [], 'method' => 'nationality'],
        'free_rooms' => ['group' => 'housekeeping', 'filters' => ['range', 'room_type', 'clean'], 'method' => 'freeRooms'],
        'room_status' => ['group' => 'housekeeping', 'filters' => ['room_type'], 'method' => 'roomStatus'],
        'out_of_order' => ['group' => 'housekeeping', 'filters' => ['date'], 'method' => 'outOfOrder'],
        'forecast' => ['group' => 'occupancy', 'filters' => ['range'], 'method' => 'forecast', 'landscape' => true],
        'manager' => ['group' => 'occupancy', 'filters' => ['date'], 'method' => 'manager'],
        'department_totals' => ['group' => 'finance', 'filters' => ['range'], 'method' => 'departmentTotals'],
        'daily_revenue' => ['group' => 'finance', 'filters' => ['range'], 'method' => 'dailyRevenue', 'landscape' => true],
        'journal' => ['group' => 'finance', 'filters' => ['date', 'user'], 'method' => 'journal', 'landscape' => true],
        'cashier' => ['group' => 'finance', 'filters' => ['range', 'user'], 'method' => 'cashier'],
        'open_balances' => ['group' => 'finance', 'filters' => [], 'method' => 'openBalances'],
        'tax' => ['group' => 'finance', 'filters' => ['range'], 'method' => 'tax'],
        'by_rate_code' => ['group' => 'rates', 'filters' => ['range'], 'method' => 'byRateCode'],
        'rate_discrepancy' => ['group' => 'rates', 'filters' => ['range'], 'method' => 'rateDiscrepancy', 'landscape' => true],
    ];

    /** Rapor grubunun yetkisi; fiyat raporları ön büro yetkisiyle görülür. */
    public static function permission(string $group): string
    {
        return 'reports.'.($group === 'rates' ? 'front_office' : $group);
    }
}
