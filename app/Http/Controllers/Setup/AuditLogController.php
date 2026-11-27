<?php

namespace App\Http\Controllers\Setup;

use App\Domain\Core\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Ayarlar → Değişiklik kaydı: kim, ne zaman, hangi kayıtta neyi değiştirdi (tarih, kullanıcı, kayıt türü, kayıt no ile süzülür). */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $p = app(Property::class);
        $today = now()->toImmutable();
        $from = BusinessDateInput::parse((string) $request->query('bas', ''), $today) ?? $today->subDays(7);
        $to = BusinessDateInput::parse((string) $request->query('bit', ''), $today) ?? $today;
        $type = (string) $request->query('tur', '');
        $user = (int) $request->query('kullanici');
        $id = (int) $request->query('kayit');

        return view('setup.audit', [
            'rows' => DB::table('pms_audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->where(fn ($q) => $q->where('a.property_id', $p->id)->orWhereNull('a.property_id'))
                ->whereBetween('a.created_at', [$from->startOfDay(), $to->endOfDay()])
                ->when($type !== '', fn ($q) => $q->where('a.auditable_type', $type))->when($user, fn ($q) => $q->where('a.user_id', $user))
                ->when($id, fn ($q) => $q->where('a.auditable_id', $id))
                ->orderByDesc('a.id')->limit(500)->get(['a.*', 'u.username']),
            'types' => DB::table('pms_audit_logs')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'users' => DB::table('users')->orderBy('username')->get(['id', 'username']),
            'from' => $from, 'to' => $to, 'type' => $type, 'user' => $user, 'id' => $id ?: '',
        ]);
    }
}
