<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Fidelio Suite8 PROISV şemasındaki 13 view'ın RS PMS karşılıkları: aynı ad, aynı kolon adları ve sırası,
 * Fidelio kod değerleri (RESERVATIONSTATUS 0/1/3, CALCULATEDSTATUS 0–13, PROFILE_INTERNALCATEGORY 1–6, CDT 1/5 …).
 * Kolon karşılıkları: docs/fidelio/kolon-kolon.md. MySQL/MariaDB ve SQLite'ta çalışır.
 */
class ProisvViews
{
    private string $driver;

    public function __construct()
    {
        $this->driver = DB::getDriverName();
    }

    /** @return array<string,string> view adı => SELECT */
    public function definitions(): array
    {
        return [
            'pro_isv_postings' => $this->postings(),
            'pro_isv_reservationinfo' => $this->reservationInfo(),
            'pro_isv_reservationrevenue' => $this->reservationRevenue(),
            'pro_isv_profileinfo' => $this->profileInfo(),
            'pro_isv_ind_fam_links' => $this->familyLinks(),
            'pro_isv_membercardinfo' => $this->memberCards(),
            'pro_isv_multiguest_list' => $this->multiGuests(),
            'pro_isv_promotions' => $this->promotions(),
            'pro_giykimbil' => $this->guestRegistration(),
            'pro_isv_daily_roombed_rmtyp' => $this->dailyRoomBeds(),
            'pro_isv_oooroombeds_roomtype' => $this->outOfOrderRoomBeds(),
            'pro_isv_virtualnumbers' => $this->virtualNumbers(),
            'pro_isv_sample_inhouse' => $this->sampleInHouse(),
        ];
    }

    public function create(): void
    {
        foreach ($this->definitions() as $name => $select) {
            DB::statement("DROP VIEW IF EXISTS {$name}");
            DB::statement("CREATE VIEW {$name} AS {$select}");
        }
    }

    public function drop(): void
    {
        foreach (array_keys($this->definitions()) as $name) {
            DB::statement("DROP VIEW IF EXISTS {$name}");
        }
    }

    // ------------------------------------------------------------------ sürücüye göre yardımcılar

    private function concat(string ...$parts): string
    {
        $parts = array_map(fn ($p) => "COALESCE({$p}, '')", $parts);

        return $this->driver === 'sqlite' ? '('.implode(' || ', $parts).')' : 'CONCAT('.implode(', ', $parts).')';
    }

    /** $format: dmy = 31.12.2026, dmyhms = 31.12.2026 14:05:00, d / m / y, julian = 2026365 */
    private function fmt(string $expr, string $format): string
    {
        $sqlite = ['dmy' => '%d.%m.%Y', 'dmyhms' => '%d.%m.%Y %H:%M:%S', 'd' => '%d', 'm' => '%m', 'y' => '%Y', 'julian' => '%Y%j'];
        $mysql = ['dmy' => '%d.%m.%Y', 'dmyhms' => '%d.%m.%Y %H:%i:%s', 'd' => '%d', 'm' => '%m', 'y' => '%Y', 'julian' => '%Y%j'];

        return $this->driver === 'sqlite' ? "strftime('{$sqlite[$format]}', {$expr})" : "DATE_FORMAT({$expr}, '{$mysql[$format]}')";
    }

    /** Tarih + saat → tarih-saat */
    private function dateTime(string $date, string $time): string
    {
        return $this->driver === 'sqlite'
            ? "datetime({$date} || ' ' || COALESCE({$time}, '00:00:00'))"
            : "TIMESTAMP({$date}, COALESCE({$time}, '00:00:00'))";
    }

    private function jsonText(string $column, string $key): string
    {
        return $this->driver === 'sqlite'
            ? "json_extract({$column}, '$.{$key}')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$key}'))";
    }

    private function comm(string $profile, string $type): string
    {
        return "(SELECT c.value FROM pms_profile_communications c WHERE c.profile_id = {$profile} AND c.type = '{$type}' ORDER BY c.is_primary DESC, c.id LIMIT 1)";
    }

    private function primaryAddressJoin(string $alias, string $profile): string
    {
        return "LEFT JOIN pms_profile_addresses {$alias} ON {$alias}.id = (SELECT x.id FROM pms_profile_addresses x WHERE x.profile_id = {$profile} ORDER BY x.is_primary DESC, x.id LIMIT 1)";
    }

    private function profileName(string $p): string
    {
        return "COALESCE({$p}.company_name, {$p}.display_name)";
    }

    private function profileType(string $p): string
    {
        return "CASE WHEN {$p}.type IN ('individual', 'contact') THEN 2 ELSE 1 END";
    }

    private function internalCategory(string $p): string
    {
        return "CASE {$p}.type WHEN 'company' THEN 1 WHEN 'travel_agent' THEN 2 WHEN 'source' THEN 3 WHEN 'individual' THEN 4 WHEN 'vendor' THEN 5 WHEN 'meeting_planner' THEN 6 WHEN 'group' THEN 1 ELSE 4 END";
    }

    private function reservationStatus(string $r): string
    {
        return "CASE WHEN {$r}.status IN ('inquiry', 'tentative', 'waitlist') THEN 0 WHEN {$r}.status IN ('cancelled', 'no_show') THEN 3 ELSE 1 END";
    }

    private function reservationStatusText(string $r): string
    {
        return "CASE WHEN {$r}.status IN ('inquiry', 'tentative', 'waitlist') THEN 'Tentative' WHEN {$r}.status IN ('cancelled', 'no_show') THEN 'Cancelled' ELSE 'Definite' END";
    }

    /** Fidelio CALCULATEDSTATUS (tesisin açık iş gününe göre). */
    private function calculatedStatus(string $r, string $bd): string
    {
        return "CASE
            WHEN {$r}.status = 'waitlist' THEN 10
            WHEN {$r}.status = 'inquiry' THEN 11
            WHEN {$r}.status = 'cancelled' THEN 12
            WHEN {$r}.status = 'no_show' THEN 13
            WHEN {$r}.is_day_use = 1 AND {$r}.status = 'in_house' THEN 8
            WHEN {$r}.is_day_use = 1 AND {$r}.status = 'checked_out' THEN 9
            WHEN {$r}.is_day_use = 1 THEN 7
            WHEN {$r}.status = 'checked_out' AND {$r}.check_out_business_date = {$bd} THEN 5
            WHEN {$r}.status = 'checked_out' THEN 6
            WHEN {$r}.status = 'in_house' AND {$r}.departure_date = {$bd} THEN 4
            WHEN {$r}.status = 'in_house' AND {$r}.check_in_business_date = {$bd} THEN 2
            WHEN {$r}.status = 'in_house' THEN 3
            WHEN {$r}.arrival_date = {$bd} THEN 1
            ELSE 0 END";
    }

    private function calculatedStatusText(string $r, string $bd): string
    {
        $labels = [0 => 'Expected', 1 => 'Expected today', 2 => 'Checked in today', 3 => 'In house', 4 => 'Expected departure today',
            5 => 'Departed today', 6 => 'History', 7 => 'Day use expected', 8 => 'Day use in house', 9 => 'Day use history',
            10 => 'Waitlist', 11 => 'Offer', 12 => 'Cancelled', 13 => 'No show'];
        $cases = implode(' ', array_map(fn ($k, $v) => "WHEN {$k} THEN '{$v}'", array_keys($labels), $labels));

        return 'CASE '.$this->calculatedStatus($r, $bd)." {$cases} END";
    }

    private function sex(string $p): string
    {
        return "CASE {$p}.gender WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END";
    }

    private function ch(string $n): string
    {
        return "CASE WHEN {$n}.is_complimentary = 1 THEN 1 WHEN {$n}.is_house_use = 1 THEN 2 ELSE NULL END";
    }

    // ------------------------------------------------------------------ view'lar

    private function postings(): string
    {
        $cdt = "CASE WHEN tc.type IN ('payment', 'deposit') OR p.kind IN ('payment', 'deposit') THEN 5 ELSE 1 END";
        $sign = "CASE WHEN {$cdt} = 5 THEN -1 ELSE 1 END";

        return "SELECT
            COALESCE(p.legacy_id, p.id) AS POSTING_ID,
            cs.cashier_id AS CASHIER_ID, ca.number AS CASHIER_NUMBER, ca.initials AS CASHIER_INITIALS,
            p.business_date AS POSTING_DATE, p.created_at AS POSTING_PCDATE,
            p.quantity AS POSTING_QUANTITY, p.unit_price AS POSTING_UNITPRICE, ({$sign}) * p.amount AS POSTING_SUMUPAMOUNT,
            p.foreign_unit_price AS POSTING_FOREIGNPRICE, ({$sign}) * p.foreign_amount AS POSTING_FOREIGNSUMUPAMOUNT,
            COALESCE(cur.short_code, p.foreign_currency) AS POSTING_CURRENCY, p.exchange_rate AS POSTING_EXCHRATE,
            cur.legacy_id AS POSTING_CURRENCY_ID,
            {$cdt} AS CDT,
            CASE tc.payment_kind WHEN 'cash' THEN 0 WHEN 'city_ledger' THEN 2 WHEN 'card' THEN 3 WHEN 'foreign_cash' THEN 4 ELSE NULL END AS PAYMENT_TYPE,
            COALESCE(p.legacy_ledger_status, CASE p.ledger WHEN 'deposit' THEN 1 WHEN 'ar' THEN 2 ELSE 0 END) AS POSTING_LEDGERSTATUS,
            COALESCE(f.legacy_id, f.id) AS POSTING_WINDOW_ID,
            p.description AS POSTING_DC_DESCRIPTION, tc.code AS POSTING_DC_NUMBER, COALESCE(tc.legacy_id, tc.id) AS POSTING_DC_ID,
            CASE WHEN f.reservation_id IS NULL THEN COALESCE(f.legacy_id, f.id) END AS POSTING_FA_ID,
            CASE WHEN f.reservation_id IS NULL THEN f.name END AS POSTING_FA_LONGDESC,
            CASE WHEN f.reservation_id IS NULL THEN f.actual_from END AS POSTING_FA_ACTVALIDFROM,
            CASE WHEN f.reservation_id IS NULL THEN f.actual_until END AS POSTING_FA_ACTVALIDUNTIL,
            CASE WHEN f.reservation_id IS NULL THEN f.valid_from END AS POSTING_FA_EXPVALIDFROM,
            CASE WHEN f.reservation_id IS NULL THEN f.valid_until END AS POSTING_FA_EXPVALIDUNTIL,
            rm.number AS ROOMNUMBER,
            COALESCE(r.legacy_id, r.id) AS POSTING_RES_ID,
            r.checked_in_at AS POSTING_RES_ACTARRTIME, r.checked_out_at AS POSTING_RES_ACTDEPTIME,
            ".$this->dateTime('r.arrival_date', 'r.eta').' AS POSTING_RES_EXPARRTIME,
            '.$this->dateTime('r.departure_date', 'r.etd').' AS POSTING_RES_EXPDEPTIME,
            rrm.number AS POSTING_RES_ROOMNUMBER,
            COALESCE(py.legacy_id, py.id, rp.legacy_id, rp.id) AS POSTING_PROFILE_ID,
            COALESCE(op.legacy_id, op.id) AS POSTING_ORG_PROFILE_ID,
            COALESCE(orr.legacy_id, orr.id) AS POSTING_ORG_RES_ID,
            COALESCE(ofo.legacy_id, ofo.id) AS POSTING_ORG_FA_ID,
            p.note AS POSTING_COMMENT,
            '.$this->profileName('ag').' AS AGENCY_NAME, COALESCE(ag.legacy_id, ag.id) AS AGENCY_ID,
            '.$this->profileName('co').' AS COMPANY_NAME, COALESCE(co.legacy_id, co.id) AS COMPANY_ID,
            '.$this->reservationStatus('r').' AS POSTING_RESSTATUS,
            pr.base_currency AS BASECURRENCY
        FROM pms_postings p
        JOIN pms_properties pr ON pr.id = p.property_id
        JOIN pms_transaction_codes tc ON tc.id = p.transaction_code_id
        JOIN pms_folios f ON f.id = p.folio_id
        LEFT JOIN pms_cashier_shifts cs ON cs.id = p.cashier_shift_id
        LEFT JOIN pms_cashiers ca ON ca.id = cs.cashier_id
        LEFT JOIN pms_currencies cur ON cur.code = p.foreign_currency
        LEFT JOIN pms_rooms rm ON rm.id = p.room_id
        LEFT JOIN pms_reservations r ON r.id = p.reservation_id
        LEFT JOIN pms_rooms rrm ON rrm.id = r.room_id
        LEFT JOIN pms_profiles rp ON rp.id = r.profile_id
        LEFT JOIN pms_profiles py ON py.id = f.payee_profile_id
        LEFT JOIN pms_profiles op ON op.id = p.origin_profile_id
        LEFT JOIN pms_reservations orr ON orr.id = p.origin_reservation_id
        LEFT JOIN pms_folios ofo ON ofo.id = p.origin_folio_id
        LEFT JOIN pms_profiles ag ON ag.id = r.agent_profile_id
        LEFT JOIN pms_profiles co ON co.id = r.company_profile_id';
    }

    private function reservationInfo(): string
    {
        $bd = 'pr.current_business_date';
        $posted = '(SELECT SUM(x.amount) FROM pms_postings x WHERE x.reservation_night_id = n.id)';
        $postedNet = '(SELECT SUM(x.net_amount) FROM pms_postings x WHERE x.reservation_night_id = n.id)';
        $postedF = '(SELECT SUM(x.foreign_amount) FROM pms_postings x WHERE x.reservation_night_id = n.id)';
        $tot = fn ($col) => "(SELECT SUM(x.{$col}) FROM pms_postings x JOIN pms_transaction_codes xt ON xt.id = x.transaction_code_id WHERE x.reservation_id = r.id AND xt.type = 'revenue')";

        return "SELECT
            COALESCE(r.legacy_id, r.id) AS RESERVATION_ID, n.stay_date AS DETAIL_DATE,
            r.arrival_date AS ARRIVALDATE, r.departure_date AS DEPARTUREDATE,
            rc.code AS HOTELSEGMENT, rc.name AS HOTELSEGMENT_LONGDESC,
            COALESCE(b.legacy_id, b.id) AS PARTY_ID, b.name AS PARTY_LONGDESC,
            rm.number AS ROOMNUMBER, rt.code AS ROOMTYPE, rt.name AS ROOMTYPE_LONGDESC, crt.code AS RATEROOMTYPE, crt.name AS RATEROOMTYPE_LONGDESC,
            1 AS DETAIL_NOOFROOMS, 1 AS NOOFROOMS,
            g.display_name AS GUESTNAME, g.first_name AS GUESTFIRSTNAME, g.middle_name AS GUESTMIDDLENAME, g.last_name AS GUESTLASTNAME,
            n.adults AS NOOFADULTS, n.free_children AS FREECHILD, n.children AS CHILDREN,
            n.child_band_1 AS CA1, n.child_band_2 AS CA2, n.child_band_3 AS CA3, n.child_other AS OTHERCA,
            v.code AS VIPCODE,
            CASE WHEN n.is_complimentary = 1 THEN 'Complimentary' WHEN n.is_house_use = 1 THEN 'House Use' END AS CHTEXT,
            rcd.code AS RATECODE, mg.code AS MAINMARKETCODE, mg.name AS MAINMARKETCODE_LONG, ms.code AS MARKETCODE, ms.name AS MARKETCODE_LONG,
            sc.code AS SOURCECODE, cc.code AS CHANNELCODE,
            n.currency_code AS CURRENCY, n.exchange_rate AS EXCHANGERATE,
            {$postedF} AS FOREIGNPOSTAMOUNT, {$posted} AS POSTAMOUNT,
            ad.country_code AS COUNTRY, nat.fidelio_code AS NATION,
            ".$this->profileName('ag').' AS AGENTNAME, COALESCE(ag.legacy_id, ag.id) AS AGENTNAME_ID,
            '.$this->profileName('co').' AS COMPANYNAME, COALESCE(co.legacy_id, co.id) AS COMPANYNAME_ID,
            '.$this->profileName('so').' AS SOURCENAME, COALESCE(bl.name, '.$this->profileName('gp').' ) AS GROUPNAME,
            r.payment_instructions AS BILLINGINFO, r.guest_comments AS REMARKS, r.external_reference AS CRSRESNR,
            pk.name AS RATECODE_BOARDTYPETEXT, r.pos_discount_code AS POSDISCOUNT,
            r.created_at AS CREATED_DATE, COALESCE(r.legacy_created_by, cu.name) AS CREATED_USER, r.sale_date AS SALEDATE,
            rty.code AS GUARANTEECODE,
            '.$this->reservationStatus('r').' AS RESERVATIONSTATUS, '.$this->reservationStatusText('r').' AS RESERVATIONSTATUSTEXT,
            '.$this->calculatedStatus('r', $bd).' AS CALCULATEDSTATUS, '.$this->calculatedStatusText('r', $bd)." AS CALCULATEDSTATUSTEXT,
            COALESCE(r.booker_name, b.booker_name) AS BOOKER,
            adc.name_en AS COUNTRY_LONGDESC, nat.nationality_tr AS NATION_LONGDESC,
            n.manual_amount AS RATEMANUAL, n.amount AS RATECODEAMOUNT, n.net_amount AS NETRATECODEAMOUNT, {$postedNet} AS NETPOSTAMOUNT,
            n.manual_foreign_amount AS FOREIGNRATEMANUAL, n.foreign_amount AS FOREIGNRATECODEAMOUNT, n.foreign_net_amount AS FOREIGNNETRATECODEAMOUNT,
            NULL AS FOREIGNNETPOSTAMOUNT,
            ".$tot('amount').' AS TOTAL_POSTAMOUNT, '.$tot('net_amount').' AS TOTAL_NETPOSTAMOUNT,
            '.$tot('foreign_amount').' AS TOTAL_FOREIGNPOSTAMOUNT, NULL AS TOTAL_FOREIGNNETPOSTAMOUNT,
            n.package_amount AS PACKAGEAMOUNT, n.package_foreign_amount AS PACKAGEFOREIGNAMOUNT,
            n.package_included_amount AS PACKAGEAMOUNT_INCLUDED, n.package_included_foreign_amount AS PACKAGEFOREIGNAMOUNT_INCLUDED,
            r.credit_limit AS CREDITLIMIT, rdr.name AS RATEDIFFREASON, '.$this->ch('n').' AS CH, rcd.package_id AS RATECODE_BOARDTYPE,
            COALESCE(n.legacy_id, n.id) AS DETAIL_ID, '.$this->profileType('g').' AS PROFILE_TYPE, COALESCE(g.legacy_id, g.id) AS PROFILE_ID,
            (SELECT COALESCE(cn.legacy_id, cn.id) FROM pms_reservation_nights cn WHERE cn.reservation_id = r.id AND cn.stay_date <= '.$bd.' ORDER BY cn.stay_date DESC LIMIT 1) AS CURRENT_DETAIL_ID,
            (SELECT COALESCE(ln.legacy_id, ln.id) FROM pms_reservation_nights ln WHERE ln.reservation_id = r.id ORDER BY ln.stay_date DESC LIMIT 1) AS LAST_DETAIL_ID,
            r.share_group_id AS SHARENUMBER,
            '.$this->concat('g.first_name', "' '", 'g.last_name').' AS GUESTNAME_BYFIRST,
            '.$this->fmt('r.arrival_date', 'julian').' AS JULIANARRIVALDATE, SUBSTR(r.eta, 1, 8) AS JULIANARRIVALTIME,
            SUBSTR(r.eta, 1, 5) AS ARRIVALTIME, SUBSTR(r.etd, 1, 5) AS DEPARTURETIME,
            '.$this->dateTime('r.arrival_date', 'r.eta').' AS ARRIVALDATETIME, '.$this->dateTime('r.departure_date', 'r.etd').' AS DEPARTUREDATETIME,
            '.$this->fmt('r.departure_date', 'julian').' AS JULIANDEPARTUREDATE, SUBSTR(r.etd, 1, 8) AS JULIANDEPARTURETIME,
            '.$this->dateTime('r.arrival_date', 'r.eta').' AS EXPECTED_ARRIVALTIME, r.checked_in_at AS ACTUAL_ARRIVALTIME,
            CASE WHEN r.checked_in_at IS NULL THEN 1 ELSE 0 END AS ACTARRTIMENULL,
            '.$this->dateTime('r.departure_date', 'r.etd').' AS EXPECTED_DEPARTURETIME, r.checked_out_at AS ACTUAL_DEPARTURETIME,
            CASE WHEN r.checked_out_at IS NULL THEN 1 ELSE 0 END AS ACTDEPTIMENULL,
            '.$this->dateTime('r.original_departure_date', 'r.etd').' AS ORIGINAL_DEPARTURETIME, r.original_departure_date AS ORIGINAL_DEPARTUREDATE,
            COALESCE(r.legacy_created_by, cu.name) AS CREATED_USERNAME, r.updated_at AS LASTUPDATETIME,
            r.updated_by AS LASTUPDATE_USER, COALESCE(r.legacy_updated_by, uu.name) AS LASTUPDATE_USERNAME,
            '.$this->profileName('ag').' AS DISPAGENTNAME, '.$this->profileName('co').' AS DISPCOMPANYNAME,
            COALESCE(bl.name, '.$this->profileName('gp').') AS DISPGROUPNAME, '.$this->profileName('so')." AS DISPSOURCENAME,
            CASE WHEN n.stay_date = r.arrival_date THEN 1 ELSE 0 END AS ROOMARRIVAL,
            CASE WHEN n.stay_date = r.arrival_date THEN n.adults ELSE 0 END AS ADULTARRIVAL,
            CASE WHEN (SELECT MAX(z.stay_date) FROM pms_reservation_nights z WHERE z.reservation_id = r.id) = n.stay_date THEN 1 ELSE 0 END AS ROOMDEPARTURE,
            CASE WHEN (SELECT MAX(z.stay_date) FROM pms_reservation_nights z WHERE z.reservation_id = r.id) = n.stay_date THEN n.adults ELSE 0 END AS ADULTDEPARTURE,
            crm.number AS CURRENTROOM, crt2.code AS CURRENTROOMTYPE,
            CASE g.gender WHEN 'male' THEN 'Male' WHEN 'female' THEN 'Female' END AS GENDER, g.birth_date AS BIRTHDAY
        FROM pms_reservation_nights n
        JOIN pms_reservations r ON r.id = n.reservation_id
        JOIN pms_properties pr ON pr.id = r.property_id
        JOIN pms_profiles g ON g.id = r.profile_id
        JOIN pms_room_types rt ON rt.id = n.room_type_id
        JOIN pms_room_types crt ON crt.id = n.charge_room_type_id
        LEFT JOIN pms_room_classes rc ON rc.id = rt.room_class_id
        LEFT JOIN pms_rooms rm ON rm.id = n.room_id
        LEFT JOIN pms_rooms crm ON crm.id = r.room_id
        LEFT JOIN pms_room_types crt2 ON crt2.id = r.room_type_id
        LEFT JOIN pms_bookings b ON b.id = r.booking_id
        JOIN pms_rate_codes rcd ON rcd.id = n.rate_code_id
        LEFT JOIN pms_packages pk ON pk.id = rcd.package_id
        JOIN pms_market_segments ms ON ms.id = n.market_segment_id
        LEFT JOIN pms_market_groups mg ON mg.id = ms.market_group_id
        JOIN pms_source_codes sc ON sc.id = r.source_code_id
        LEFT JOIN pms_channel_codes cc ON cc.id = r.channel_code_id
        LEFT JOIN pms_vip_levels v ON v.id = r.vip_level_id
        LEFT JOIN pms_reservation_types rty ON rty.id = r.reservation_type_id
        LEFT JOIN pms_reason_codes rdr ON rdr.id = n.override_reason_code_id
        LEFT JOIN pms_profiles ag ON ag.id = r.agent_profile_id
        LEFT JOIN pms_profiles co ON co.id = r.company_profile_id
        LEFT JOIN pms_profiles so ON so.id = r.source_profile_id
        LEFT JOIN pms_profiles gp ON gp.id = r.group_profile_id
        LEFT JOIN pms_blocks bl ON bl.id = r.block_id
        LEFT JOIN pms_countries nat ON nat.code = g.nationality_code
        ".$this->primaryAddressJoin('ad', 'g.id').'
        LEFT JOIN pms_countries adc ON adc.code = ad.country_code
        LEFT JOIN users cu ON cu.id = r.created_by
        LEFT JOIN users uu ON uu.id = r.updated_by';
    }

    private function reservationRevenue(): string
    {
        $bd = 'pr.current_business_date';
        $sum = function (string $condition, string $col) {
            return "SUM(CASE WHEN {$condition} THEN p.{$col} ELSE 0 END)";
        };
        $bucket = fn (string $b) => "tc.revenue_bucket = '{$b}'";
        $buckets = [
            'ROOM' => $bucket('room'), 'FB' => "tc.revenue_bucket IN ('food', 'beverage')", 'MINIBAR' => $bucket('minibar'),
            'TELEPHONE' => $bucket('telephone'), 'MISC' => "(tc.revenue_bucket IS NULL OR tc.revenue_bucket IN ('other', 'spa', 'laundry'))",
            'BREAKFAST' => $bucket('breakfast'), 'LUNCH' => $bucket('lunch'), 'DINNER' => $bucket('dinner'),
            'ALLINCFOOD' => $bucket('allinc_food'), 'ALLINCBVRG' => $bucket('allinc_beverage'),
        ];
        $agg = [];
        foreach ($buckets as $name => $cond) {
            $agg[] = $sum($cond, 'amount')." AS GROSS{$name}";
        }
        foreach ($buckets as $name => $cond) {
            $agg[] = $sum($cond, 'net_amount')." AS NET{$name}";
        }
        $aggSql = implode(",\n            ", $agg);
        $grossCols = implode(', ', array_map(fn ($b) => "a.GROSS{$b}", array_keys($buckets)));
        $netCols = implode(', ', array_map(fn ($b) => "a.NET{$b}", array_keys($buckets)));

        return 'SELECT
            COALESCE(r.legacy_id, r.id) AS RESERVATION_ID, a.FA_ID AS FA_ID, a.DDATE AS DDATE,
            '.$this->fmt('a.DDATE', 'd').' AS CDAY, '.$this->fmt('a.DDATE', 'm').' AS CMONTH, '.$this->fmt('a.DDATE', 'y').' AS CYEAR,
            COALESCE(g.legacy_id, g.id) AS PROFILE_ID, COALESCE(n.legacy_id, n.id) AS DETAIL_ID,
            COALESCE(rt.legacy_id, rt.id) AS ROOMTYPE_ID, rt.code AS ROOMTYPE, crt.code AS RATEROOMTYPE,
            rc.name AS ROOMTYPE_SEGMENT, crc.name AS RATEROOMTYPE_SEGMENT,
            n.currency_code AS CURRENCY, n.exchange_rate AS EXCHANGERATE, COALESCE(g.display_name, a.FA_NAME) AS GUESTNAME,
            r.arrival_date AS ARRDATE, '.$this->dateTime('r.arrival_date', 'r.eta').' AS ARRTIME,
            r.departure_date AS DEPDATE, '.$this->dateTime('r.departure_date', 'r.etd').' AS DEPTIME,
            SUBSTR(r.eta, 1, 5) AS ARRIVALTIME, SUBSTR(r.etd, 1, 5) AS DEPARTURETIME,
            '.$this->dateTime('r.arrival_date', 'r.eta').' AS ARRIVALDATETIME, '.$this->dateTime('r.departure_date', 'r.etd').' AS DEPARTUREDATETIME,
            '.$this->reservationStatus('r').' AS RESERVATION_STATUS, '.$this->reservationStatusText('r').' AS RESERVATIONSTATUSTEXT,
            '.$this->calculatedStatus('r', $bd).' AS CALCSTATUS, '.$this->calculatedStatusText('r', $bd).' AS CALCULATEDSTATUSTEXT,
            CASE WHEN r.id IS NULL THEN NULL ELSE 1 END AS NOOFROOMS, n.adults AS NOOFADULTS, n.children AS CHILDREN, n.free_children AS FREECHILD,
            n.child_band_1 AS CA1, n.child_band_2 AS CA2, n.child_band_3 AS CA3, n.child_other AS OTHERCA, '.$this->ch('n').' AS CH,
            ms.code AS MARKETCODE, mg.code AS MAINMARKETCODE, sc.code AS SOURCECODE, cc.code AS CHANNELCODE, rcd.code AS RATECODE,
            rm.number AS ROOMNUMBER,
            '.$this->profileName('ag').' AS AGENTNAME, '.$this->profileName('co').' AS COMPANYNAME, '.$this->profileName('so').' AS SOURCENAME,
            COALESCE(bl.name, '.$this->profileName('gp').") AS GROUPNAME,
            COALESCE(bl.legacy_id, bl.id) AS BLOCK_ID, bl.code AS BLOCK_NAME,
            nat.fidelio_code AS NATION, nat.nationality_tr AS NATION_LONGDESC, v.code AS VIPCODE,
            rcd.package_id AS BOARDTYPE, pk.name AS BOARDTYPETEXT, r.pos_discount_code AS POSDISCOUNT,
            a.POSTAMOUNT AS POSTAMOUNT, a.NETPOSTAMOUNT AS NETPOSTAMOUNT, n.amount AS RATECODEAMOUNT, n.net_amount AS NETRATECODEAMOUNT,
            {$grossCols},
            {$netCols},
            COALESCE(r.created_at, a.FA_CREATED) AS CREATED_DATE, r.sale_date AS BOOKING_DATE, r.created_by AS CREATED_USER_ID,
            COALESCE(r.legacy_created_by, cu.name) AS CREATED_USER,
            ad.country_code AS COUNTRY, adc.name_en AS COUNTRY_LONGDESC, ".$this->profileType('g')." AS PROFILE_TYPE
        FROM (
            SELECT p.property_id, p.reservation_id,
                CASE WHEN f.reservation_id IS NULL THEN COALESCE(f.legacy_id, f.id) END AS FA_ID,
                MAX(CASE WHEN f.reservation_id IS NULL THEN f.name END) AS FA_NAME,
                MIN(CASE WHEN f.reservation_id IS NULL THEN f.created_at END) AS FA_CREATED,
                p.business_date AS DDATE,
                SUM(p.amount) AS POSTAMOUNT, SUM(p.net_amount) AS NETPOSTAMOUNT,
                {$aggSql}
            FROM pms_postings p
            JOIN pms_transaction_codes tc ON tc.id = p.transaction_code_id
            JOIN pms_folios f ON f.id = p.folio_id
            WHERE tc.type = 'revenue'
            GROUP BY p.property_id, p.reservation_id, CASE WHEN f.reservation_id IS NULL THEN COALESCE(f.legacy_id, f.id) END, p.business_date
        ) a
        JOIN pms_properties pr ON pr.id = a.property_id
        LEFT JOIN pms_reservations r ON r.id = a.reservation_id
        LEFT JOIN pms_reservation_nights n ON n.reservation_id = r.id AND n.stay_date = a.DDATE
        LEFT JOIN pms_profiles g ON g.id = r.profile_id
        LEFT JOIN pms_room_types rt ON rt.id = COALESCE(n.room_type_id, r.room_type_id)
        LEFT JOIN pms_room_types crt ON crt.id = COALESCE(n.charge_room_type_id, r.charge_room_type_id)
        LEFT JOIN pms_room_classes rc ON rc.id = rt.room_class_id
        LEFT JOIN pms_room_classes crc ON crc.id = crt.room_class_id
        LEFT JOIN pms_rooms rm ON rm.id = COALESCE(n.room_id, r.room_id)
        LEFT JOIN pms_rate_codes rcd ON rcd.id = COALESCE(n.rate_code_id, r.rate_code_id)
        LEFT JOIN pms_packages pk ON pk.id = rcd.package_id
        LEFT JOIN pms_market_segments ms ON ms.id = COALESCE(n.market_segment_id, r.market_segment_id)
        LEFT JOIN pms_market_groups mg ON mg.id = ms.market_group_id
        LEFT JOIN pms_source_codes sc ON sc.id = r.source_code_id
        LEFT JOIN pms_channel_codes cc ON cc.id = r.channel_code_id
        LEFT JOIN pms_vip_levels v ON v.id = r.vip_level_id
        LEFT JOIN pms_profiles ag ON ag.id = r.agent_profile_id
        LEFT JOIN pms_profiles co ON co.id = r.company_profile_id
        LEFT JOIN pms_profiles so ON so.id = r.source_profile_id
        LEFT JOIN pms_profiles gp ON gp.id = r.group_profile_id
        LEFT JOIN pms_blocks bl ON bl.id = r.block_id
        LEFT JOIN pms_countries nat ON nat.code = g.nationality_code
        ".$this->primaryAddressJoin('ad', 'g.id').'
        LEFT JOIN pms_countries adc ON adc.code = ad.country_code
        LEFT JOIN users cu ON cu.id = r.created_by';
    }

    private function profileInfo(): string
    {
        return 'SELECT
            COALESCE(p.legacy_id, p.id) AS PROFILE_ID, p.legacy_global_id AS PROFILE_GLOBALID,
            '.$this->profileType('p').' AS PROFILE_TYPE, '.$this->internalCategory('p').' AS PROFILE_INTERNALCATEGORY,
            p.display_name AS GUESTFULLNAME, '.$this->concat('p.first_name', "' '", 'p.last_name').' AS GUESTFULLNAME_BYFIRST,
            COALESCE(p.last_name, p.company_name, p.display_name) AS NAME1, p.first_name AS NAME2, p.middle_name AS NAME3,
            p.salutation AS ADDRESSGREETING, p.letter_greeting AS LETTERGREETING, '.$this->sex('p').' AS SEX,
            p.language_code AS PROFILELANGUAGE, p.birth_date AS BIRTHDATE, p.wedding_date AS WEDDINGDAY,
            ad.line1 AS STREET1, ad.line2 AS STREET2, ad.line3 AS STREET3, ad.city AS CITY, ad.postal_code AS ZIPCODE,
            ad.state AS STATE_SHORTDESC, ad.state AS STATE_LONGDESC, ac.fidelio_code AS COUNTRY, ac.name_en AS COUNTRY_LONGDESC,
            nat.fidelio_code AS NATION, nat.nationality_tr AS NATION_LONGDESC,
            '.$this->comm('p.id', 'email').' AS EMAIL, '.$this->comm('p.id', 'phone').' AS TELEPHONE, '.$this->comm('p.id', 'fax').' AS FAX,
            '.$this->comm('p.id', 'mobile').' AS MOBILETELEPHONE, '.$this->comm('p.id', 'web')." AS WEBSITE,
            v.code AS VIPCODE, p.tax_office AS TAXOFFICE, p.tax_number AS TAXNUMBER,
            p.created_at AS CREATIONDATE, p.created_by AS CREATED_USER,
            CASE p.status WHEN 'inactive' THEN 0 WHEN 'terminated' THEN 3 WHEN 'merged' THEN 4 ELSE 1 END AS ACTIVE_STATUS,
            p.updated_at AS LASTUPDATETIME, p.updated_by AS LASTUPDATEUSER, COALESCE(p.legacy_updated_by, uu.name) AS LASTUPDATEUSERNAME,
            p.origin_hotel_name AS ORIGINATED_HOTEL_NAME, p.origin_hotel_legacy_id AS ORIGINATED_HOTEL_ID,
            COALESCE(pc.legacy_id, pc.id) AS PRIMARY_CONTACT_ID, COALESCE(mp.legacy_id, mp.id) AS MERGEDTO_PROFILE_ID, p.merged_at AS MERGETIME,
            idn.document_type AS PERSONAL_DOC_TYPE, NULL AS PERSONAL_DOC_NUMBER, NULL AS TCIDNO,
            p.occupation AS OCCUPATION,
            CASE WHEN p.no_mailing = 1 THEN 1 ELSE 0 END AS NOMAILING, CASE WHEN p.no_emailing = 1 THEN 1 ELSE 0 END AS NOEMAILING,
            CASE WHEN p.store_private_data = 1 THEN 1 ELSE 0 END AS STOREPRIVATEDATA
        FROM pms_profiles p
        ".$this->primaryAddressJoin('ad', 'p.id').'
        LEFT JOIN pms_countries ac ON ac.code = ad.country_code
        LEFT JOIN pms_countries nat ON nat.code = p.nationality_code
        LEFT JOIN pms_vip_levels v ON v.id = p.vip_level_id
        LEFT JOIN pms_profiles pc ON pc.id = p.primary_contact_profile_id
        LEFT JOIN pms_profiles mp ON mp.id = p.merged_into_id
        LEFT JOIN pms_profile_identities idn ON idn.id = (SELECT i.id FROM pms_profile_identities i WHERE i.profile_id = p.id ORDER BY i.id DESC LIMIT 1)
        LEFT JOIN users uu ON uu.id = p.updated_by
        WHERE p.deleted_at IS NULL';
    }

    private function familyLinks(): string
    {
        return "SELECT
            COALESCE(p.legacy_id, p.id) AS PROFILE_ID, p.legacy_global_id AS PROFILE_GLOBAL_ID,
            COALESCE(l.legacy_id, l.id) AS LINKED_PROFILE_ID, l.legacy_global_id AS LINKED_PROFILE_GLOBAL_ID,
            CASE l.status WHEN 'inactive' THEN 0 WHEN 'terminated' THEN 3 WHEN 'merged' THEN 4 ELSE 1 END AS LP_ACTIVE_STATUS,
            COALESCE(l.last_name, l.company_name) AS LP_NAME1, l.display_name AS LP_FORMATTEDNAME, rel.relation AS LINK_TYPE
        FROM pms_profile_relations rel
        JOIN pms_profiles p ON p.id = rel.profile_id
        JOIN pms_profiles l ON l.id = rel.related_profile_id";
    }

    private function memberCards(): string
    {
        return "SELECT
            COALESCE(p.legacy_id, p.id) AS PROFILE_ID,
            CASE mt.role WHEN 'ar_number' THEN 1 WHEN 'iata' THEN 2 WHEN 'vat' THEN 3 WHEN 'loyalty' THEN 4 WHEN 'corporate_id' THEN 5 ELSE 6 END AS CARDROLE,
            mt.code AS SHORTDESC, mt.name AS LONGDESC, m.number AS CARDVALUE
        FROM pms_profile_memberships m
        JOIN pms_membership_types mt ON mt.id = m.membership_type_id
        JOIN pms_profiles p ON p.id = m.profile_id";
    }

    private function multiGuests(): string
    {
        return "SELECT COALESCE(r.legacy_id, r.id) AS RESERVATION_ID, COALESCE(p.legacy_id, p.id) AS MULTIGUEST_PROFILE_ID
        FROM pms_reservation_guests rg
        JOIN pms_reservations r ON r.id = rg.reservation_id
        JOIN pms_profiles p ON p.id = rg.profile_id
        WHERE rg.role <> 'primary'";
    }

    private function promotions(): string
    {
        return "SELECT
            COALESCE(n.legacy_id, n.id) AS RESDETAIL_ID, n.stay_date AS RESDETAIL_DATE,
            pm.code AS PROM_SHORTDESC, pm.name AS PROM_LONGDESC, pm.rule_code AS RULE_SHORTDESC, pm.rule_name AS RULE_LONGDESC,
            CASE pm.rule_type WHEN 'free_nights' THEN 1 WHEN 'percent' THEN 2 ELSE 3 END AS RULE_TYPE,
            np.amount AS LOCAL_AMOUNT, np.foreign_amount AS FOREIGN_AMOUNT
        FROM pms_reservation_night_promotions np
        JOIN pms_reservation_nights n ON n.id = np.reservation_night_id
        JOIN pms_promotions pm ON pm.id = np.promotion_id";
    }

    private function guestRegistration(): string
    {
        $guests = "SELECT r.id AS reservation_id, r.profile_id AS profile_id, 0 AS ord, r.checked_in_at AS cin, r.checked_out_at AS cout FROM pms_reservations r
            UNION ALL
            SELECT rg.reservation_id, rg.profile_id, rg.id, rg.checked_in_at, rg.checked_out_at FROM pms_reservation_guests rg WHERE rg.role <> 'primary'";

        return 'SELECT
            ROW_NUMBER() OVER (ORDER BY r.arrival_date, r.id, x.ord) AS SIRANO,
            COALESCE(p.legacy_id, p.id) AS PROFILE_ID,
            (SELECT COALESCE(fn.legacy_id, fn.id) FROM pms_reservation_nights fn WHERE fn.reservation_id = r.id ORDER BY fn.stay_date LIMIT 1) AS YDET_ID,
            NULL AS TCKIMLIKNO,
            '.$this->jsonText('idn.registry', 'hes_code').' AS HES_CODE,
            p.last_name AS SOYADI, p.first_name AS ADI, p.father_name AS BABAADI, p.mother_name AS ANAADI, p.birth_place AS DOGUMYERI,
            '.$this->fmt('p.birth_date', 'dmy').' AS DOGUMTARIHI, '.$this->fmt('p.birth_date', 'dmy')." AS DOGUMTARIHI2,
            nat.legacy_id AS XCMS_XCOU_ID_NATION, nat.fidelio_code AS UYRUGU, nat.kbs_code AS UYRUK_KODU,
            CASE idn.document_type WHEN 'passport' THEN 'P' WHEN 'driving_license' THEN 'E' WHEN 'id_card' THEN 'K' WHEN 'tc_id' THEN 'K' WHEN 'kktc_id' THEN 'K' END AS KIMLIKBELGESITURU,
            idn.serial_number AS KIMLIKSERINO,
            ".$this->jsonText('idn.registry', 'il').' AS NUFUSAKAYITLIOLDUGUIL,
            '.$this->jsonText('idn.registry', 'ilce').' AS NUFUSAKAYITLIOLDUGUILCE,
            '.$this->jsonText('idn.registry', 'mahalle').' AS NUFUSAKAYITLIOLDUGUMAHALLE,
            '.$this->jsonText('idn.registry', 'cilt').' AS NUFUSCILT,
            '.$this->jsonText('idn.registry', 'aile_sira').' AS NUFUSAILESIRA,
            '.$this->jsonText('idn.registry', 'sira_no')." AS NUFUSSIRANO,
            CASE p.gender WHEN 'male' THEN 'E' WHEN 'female' THEN 'K' END AS CINSIYET,
            CASE p.marital_status WHEN 'married' THEN 'E' WHEN 'single' THEN 'B' END AS MEDENIHALI,
            p.occupation AS ISI,
            ".$this->concat('ad.line1', "' '", 'ad.line2', "' '", 'ad.city').' AS IKAMETADRESI,
            '.$this->comm('p.id', 'phone').' AS TELEPHONE, '.$this->comm('p.id', 'mobile').' AS MOBILETELEPHONE,
            '.$this->fmt('COALESCE(x.cin, r.checked_in_at, r.arrival_date)', 'dmyhms').' AS GELISTARIHI,
            COALESCE(x.cin, r.checked_in_at, r.arrival_date) AS GELISTARIHI2,
            '.$this->fmt('COALESCE(x.cout, r.checked_out_at, r.departure_date)', 'dmyhms').' AS AYRILISTARIHI,
            COALESCE(x.cout, r.checked_out_at, r.departure_date) AS AYRILISTARIHI2,
            rm.number AS VERILENODANO, r.vehicle_plate AS ARACPLAKANO, pr.code AS RESORT,
            COALESCE(rc.legacy_id, rc.id) AS YCAD_ID, rc.name AS YCAD_LONGDESC
        FROM ('.$guests.') x
        JOIN pms_reservations r ON r.id = x.reservation_id
        JOIN pms_properties pr ON pr.id = r.property_id
        JOIN pms_profiles p ON p.id = x.profile_id
        LEFT JOIN pms_rooms rm ON rm.id = r.room_id
        LEFT JOIN pms_room_types rt ON rt.id = r.room_type_id
        LEFT JOIN pms_room_classes rc ON rc.id = rt.room_class_id
        LEFT JOIN pms_countries nat ON nat.code = p.nationality_code
        LEFT JOIN pms_profile_identities idn ON idn.id = (SELECT i.id FROM pms_profile_identities i WHERE i.profile_id = p.id ORDER BY i.id DESC LIMIT 1)
        '.$this->primaryAddressJoin('ad', 'p.id')."
        WHERE r.status IN ('in_house', 'checked_out')";
    }

    private function dailyRoomBeds(): string
    {
        return 'SELECT
            COALESCE(rt.legacy_id, rt.id) AS ROOMTYPE_ID, i.date AS DDATE, rc.code AS HOTELSEGMENT,
            rt.code AS ROOMTYPE_SHORTDESC, rt.name AS ROOMTYPE_LONGDESC,
            i.physical_rooms AS NOOFROOMS, i.physical_rooms * rt.beds AS NOOFBEDS
        FROM pms_inventory_daily i
        JOIN pms_room_types rt ON rt.id = i.room_type_id
        LEFT JOIN pms_room_classes rc ON rc.id = rt.room_class_id';
    }

    private function outOfOrderRoomBeds(): string
    {
        return 'SELECT
            COALESCE(rt.legacy_id, rt.id) AS ROOMTYPE_ID, i.date AS DDATE, rc.code AS HOTELSEGMENT,
            rt.code AS ROOMTYPE_SHORTDESC, rt.name AS ROOMTYPE_LONGDESC,
            i.out_of_service AS OOSROOMS, i.out_of_service * rt.beds AS OOSBEDS,
            i.out_of_order AS OOOROOMS, i.out_of_order * rt.beds AS OOOBEDS
        FROM pms_inventory_daily i
        JOIN pms_room_types rt ON rt.id = i.room_type_id
        LEFT JOIN pms_room_classes rc ON rc.id = rt.room_class_id';
    }

    private function virtualNumbers(): string
    {
        return 'SELECT COALESCE(r.legacy_id, r.id) AS RESERVATION_ID, vn.number AS VIRTUALNUMBER,
            CASE WHEN a.is_active = 1 THEN 1 ELSE 0 END AS ISINUSE
        FROM pms_virtual_number_assignments a
        JOIN pms_virtual_numbers vn ON vn.id = a.virtual_number_id
        JOIN pms_reservations r ON r.id = a.reservation_id';
    }

    private function sampleInHouse(): string
    {
        return "SELECT COALESCE(r.legacy_id, r.id) AS RESERVATION_ID, r.arrival_date AS ARRIVALDATE, r.departure_date AS DEPARTUREDATE,
            rm.number AS ROOMNUMBER, g.display_name AS GUESTNAME
        FROM pms_reservations r
        JOIN pms_profiles g ON g.id = r.profile_id
        LEFT JOIN pms_rooms rm ON rm.id = r.room_id
        WHERE r.status = 'in_house'";
    }
}
