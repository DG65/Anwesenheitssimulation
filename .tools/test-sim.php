<?php
/**
 * test-sim.php — Prüfstand für die Anwesenheitssimulation OHNE laufendes Symcon.
 *
 * Simuliert IPSModule (Properties/Attribute/Variablen/Timer/Nachrichten) und
 * die benötigten IPS_*-Kernfunktionen, spielt dann vier Lernwochen plus einen
 * kompletten Simulationstag durch: Lernen (Dedupe, Zusammenfassen, Trimmen),
 * kein Lernen während der Wiedergabe, Vorbild-Tag-Wahl (gemeinsam/eigen),
 * Einschwingen, minütliche Wiedergabe mit Versatz, Tageswechsel, Auslöser-
 * Variable, Sonnenuntergangs-Bezug, Vergessen, Resync-Fail-safe, Formular- und
 * Vertrags-Hygiene.
 *
 * Aufruf:   php .tools/test-sim.php
 * Rückgabe: 0 = alle Blöcke bestanden, 1 = mindestens ein Fehlschlag
 */

date_default_timezone_set('Europe/Berlin');

const VM_UPDATE = 10603;
const KR_READY = 10103;
const IPS_KERNELMESSAGE = 10100;
const VARIABLETYPE_BOOLEAN = 0;
const VARIABLETYPE_INTEGER = 1;
const VARIABLETYPE_FLOAT = 2;
const VARIABLETYPE_STRING = 3;

$GLOBALS['VARS'] = [];      // id => [type, value, name, ident, parent, hidden, action]
$GLOBALS['NEXT_ID'] = 50000;
$GLOBALS['ACTIONS'] = [];   // [ts, id, value, via]
$GLOBALS['LOG'] = [];
$GLOBALS['LOC'] = [];       // Location-Control-Instanzen

function mkvar(int $id, int $type, $value, string $name, string $ident = '', int $parent = 0, bool $action = false): void
{
    $GLOBALS['VARS'][$id] = ['type' => $type, 'value' => $value, 'name' => $name, 'ident' => $ident, 'parent' => $parent, 'hidden' => false, 'action' => $action];
}
$GLOBALS['RUNLEVEL'] = KR_READY; // im Block "Symcon-Start" vorübergehend KR_INIT
const KR_INIT = 10102;
function IPS_GetKernelRunlevel(): int { return $GLOBALS['RUNLEVEL']; }
function IPS_VariableExists($id): bool { return isset($GLOBALS['VARS'][(int)$id]); }
// Profile: $GLOBALS['PROFILES'][VariablenID] = [Standardprofil, eigenes Profil]
$GLOBALS['PROFILES'] = [];
function IPS_GetVariable($id): array { return ['VariableType' => $GLOBALS['VARS'][(int)$id]['type'], 'VariableProfile' => $GLOBALS['PROFILES'][(int)$id][0] ?? '', 'VariableCustomProfile' => $GLOBALS['PROFILES'][(int)$id][1] ?? '']; }
function GetValue($id) { return $GLOBALS['VARS'][(int)$id]['value']; }
function GetValueString($id): string { return (string)$GLOBALS['VARS'][(int)$id]['value']; }
function SetValue($id, $v): bool { $GLOBALS['VARS'][(int)$id]['value'] = $v; $GLOBALS['ACTIONS'][] = [$GLOBALS['CLOCK'], (int)$id, $v, 'SetValue']; return true; }
function SetValueString($id, string $v): bool { $GLOBALS['VARS'][(int)$id]['value'] = $v; return true; }
function HasAction($id): bool { return (bool)$GLOBALS['VARS'][(int)$id]['action']; }
function RequestAction($id, $v): bool { if (!empty($GLOBALS['FAIL_RA'])) { return false; } $GLOBALS['VARS'][(int)$id]['value'] = $v; $GLOBALS['ACTIONS'][] = [$GLOBALS['CLOCK'], (int)$id, $v, 'RequestAction']; return true; }
// Bewusst KEIN IPS_LogMessage()-Stub: ein Aufruf im Modul wäre ein Fatal Error
// (SUITE.md Store-Review Punkt 15 — nur $this->LogMessage in Modulklassen).
const KL_MESSAGE = 10201; const KL_NOTIFY = 10203; const KL_WARNING = 10204; const KL_ERROR = 10205; const KL_DEBUG = 10206; const KL_CUSTOM = 10207;
// Kachel-Visualisierungen ($GLOBALS['VISU']) und WebFronts ($GLOBALS['WFC']) als Push-Ziele; gesendete Meldungen in $GLOBALS['PUSH'].
$GLOBALS['VISU'] = []; $GLOBALS['WFC'] = []; $GLOBALS['PUSH'] = [];
function IPS_GetInstanceListByModuleID(string $guid): array {
    switch ($guid) {
        case '{43192F0B-135B-4CE7-A0A7-1475603F3060}': return $GLOBALS['ARCHIVE_INST'] ?? [];
        case '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}': return $GLOBALS['VISU'];
        case '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}': return $GLOBALS['WFC'];
        default: return array_keys($GLOBALS['LOC']);
    }
}
function VISU_PostNotificationEx(int $id, string $title, string $text, string $icon, string $sound, int $target): bool { if (!empty($GLOBALS['FAIL_PUSH'])) { return false; } $GLOBALS['PUSH'][] = ['visu', $id, $title, $text, $icon, $sound, $target]; return true; }
function WFC_PushNotification(int $id, string $title, string $text, string $sound, int $target): bool { if (!empty($GLOBALS['FAIL_PUSH'])) { return false; } $GLOBALS['PUSH'][] = ['wfc', $id, $title, $text, '', $sound, $target]; return true; }
// Archive Control: $GLOBALS['ARCHIVE'][VariablenID] = [[Zeitstempel, Wert], …]; Rückgabe wie Symcon: neueste zuerst, höchstens 10000.
$GLOBALS['ARCHIVE'] = []; $GLOBALS['ARCHIVE_INST'] = [];
function AC_GetLoggingStatus(int $ac, int $vid): bool { return isset($GLOBALS['ARCHIVE'][$vid]); }
function AC_GetLoggedValues(int $ac, int $vid, int $from, int $to, int $limit): array {
    $out = []; foreach ($GLOBALS['ARCHIVE'][$vid] ?? [] as [$t, $v]) { if ($t >= $from && $t <= $to) { $out[] = ['TimeStamp' => $t, 'Value' => $v, 'Duration' => 0]; } }
    usort($out, fn($a, $b) => $b['TimeStamp'] <=> $a['TimeStamp']); return array_slice($out, 0, 10000);
}
function IPS_GetConfiguration(int $id): string { return json_encode(['Location' => json_encode(['latitude' => 48.47, 'longitude' => 7.94])]); }
function IPS_GetObjectIDByIdent(string $ident, int $parent) {
    foreach ($GLOBALS['VARS'] as $id => $v) { if ($v['ident'] === $ident && $v['parent'] === $parent) { return $id; } }
    return false;
}
function IPS_GetVariableList(): array { return array_keys($GLOBALS['VARS']); }
function IPS_GetChildrenIDs(int $parent): array { $o = []; foreach ($GLOBALS['VARS'] as $id => $v) { if ($v['parent'] === $parent) { $o[] = $id; } } return $o; }
function IPS_GetObject(int $id): array { return ['ObjectIdent' => $GLOBALS['VARS'][$id]['ident'] ?? '', 'ObjectName' => $GLOBALS['VARS'][$id]['name'] ?? '']; }
function IPS_DeleteVariable(int $id): bool { unset($GLOBALS['VARS'][$id]); return true; }
function IPS_SetHidden(int $id, bool $h): bool { $GLOBALS['VARS'][$id]['hidden'] = $h; return true; }
function IPS_GetName(int $id): string { return $GLOBALS['VARS'][$id]['name'] ?? ($GLOBALS['INSTANCES'][$id] ?? ''); }
// Instanzen (Geräte) fürs Benennen der Vorschläge: $GLOBALS['INSTANCES'][ID] = Name
$GLOBALS['INSTANCES'] = [];
function IPS_GetParent(int $id): int { return (int)($GLOBALS['VARS'][$id]['parent'] ?? 0); }
function IPS_InstanceExists(int $id): bool { return isset($GLOBALS['INSTANCES'][$id]); }
function IPS_GetLocation(int $id): string { $p = IPS_GetParent($id); return (isset($GLOBALS['INSTANCES'][$p]) ? 'Haus\\' . $GLOBALS['INSTANCES'][$p] . '\\' : 'Haus\\') . IPS_GetName($id); }
function GetValueFormatted(int $id): string { $v = $GLOBALS['VARS'][$id]['value']; return $GLOBALS['FORMATTED'][$id][(string)$v] ?? (is_bool($v) ? ($GLOBALS['LANG'] === 'de' ? ($v ? 'An' : 'Aus') : ($v ? 'On' : 'Off')) : (string)$v); }
function AC_GetAggregationVariables(int $ac, bool $calc): array { $o = []; foreach (array_keys($GLOBALS['ARCHIVE']) as $vid) { $o[] = ['VariableID' => $vid, 'AggregationType' => 0, 'AggregationActive' => true]; } return $o; }
// Echte Version aus library.json (mit Beta-Suffix, damit baseVersion() mitgeprüft wird)
function IPS_GetLibrary(string $guid): array { $lib = json_decode(file_get_contents(dirname(__DIR__) . '/library.json'), true); return ['Version' => $lib['version'] . '-beta.1', 'Build' => (int)$lib['build']]; }
// Sprache: der Prüfstand läuft auf Deutsch (deutsche Tabelle aus locale.json, wie ein deutsches
// Symcon), Block 15 schaltet auf Englisch (Quelltext bleibt, wie ein englisches Symcon).
$GLOBALS['DE'] = json_decode(file_get_contents(dirname(__DIR__) . '/DG65Anwesenheitssimulation/locale.json'), true)['translations']['de'] ?? [];
$GLOBALS['LANG'] = 'de';

class IPSModule
{
    public int $InstanceID = 12345;
    public array $props = [];
    public array $attrs = [];
    public int $status = 0;
    public array $timers = [];
    public array $messages = [];
    public array $debug = [];
    public array $fieldUpdates = [];
    public int $reloads = 0;

    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterPropertyString(string $n, string $d): void { if (!array_key_exists($n, $this->props)) { $this->props[$n] = $d; } }
    public function RegisterPropertyInteger(string $n, int $d): void { if (!array_key_exists($n, $this->props)) { $this->props[$n] = $d; } }
    public function RegisterPropertyBoolean(string $n, bool $d): void { if (!array_key_exists($n, $this->props)) { $this->props[$n] = $d; } }
    public function ReadPropertyString(string $n): string { return $this->props[$n]; }
    public function ReadPropertyInteger(string $n): int { return $this->props[$n]; }
    public function ReadPropertyBoolean(string $n): bool { return $this->props[$n]; }
    public function RegisterPropertyFloat(string $n, float $d): void { if (!array_key_exists($n, $this->props)) { $this->props[$n] = $d; } }
    public function ReadPropertyFloat(string $n): float { return (float)$this->props[$n]; }
    public function RegisterAttributeString(string $n, string $d): void { if (!array_key_exists($n, $this->attrs)) { $this->attrs[$n] = $d; } }
    public function RegisterAttributeInteger(string $n, int $d): void { if (!array_key_exists($n, $this->attrs)) { $this->attrs[$n] = $d; } }
    public function RegisterAttributeBoolean(string $n, bool $d): void { if (!array_key_exists($n, $this->attrs)) { $this->attrs[$n] = $d; } }
    public function ReadAttributeString(string $n): string { return $this->attrs[$n]; }
    public function ReadAttributeInteger(string $n): int { return $this->attrs[$n]; }
    public function ReadAttributeBoolean(string $n): bool { return $this->attrs[$n]; }
    public function WriteAttributeString(string $n, string $v): void { $this->attrs[$n] = $v; }
    public function WriteAttributeInteger(string $n, int $v): void { $this->attrs[$n] = $v; }
    public function WriteAttributeBoolean(string $n, bool $v): void { $this->attrs[$n] = $v; }
    public function SetStatus(int $s): void { $this->status = $s; }
    public function RegisterTimer(string $n, int $ms, string $script): void { $this->timers[$n] = $ms; }
    public function SetTimerInterval(string $n, int $ms): void { $this->timers[$n] = $ms; }
    public function RegisterMessage(int $sender, int $msg): void { $this->messages[$sender][$msg] = true; }
    public function UnregisterMessage(int $sender, int $msg): void { unset($this->messages[$sender][$msg]); if (empty($this->messages[$sender])) { unset($this->messages[$sender]); } }
    public function GetMessageList(): array { $o = []; foreach ($this->messages as $s => $m) { $o[$s] = array_keys($m); } return $o; }
    public function SendDebug(string $a, string $b, int $c): void { $this->debug[] = "$a: $b"; }
    public function LogMessage(string $m, int $type): void { $GLOBALS['LOG'][] = "[$type] $m"; }
    public function Translate(string $t): string { $GLOBALS['TRANSLATE_CALLS'] = ($GLOBALS['TRANSLATE_CALLS'] ?? 0) + 1; return $GLOBALS['LANG'] === 'de' ? ($GLOBALS['DE'][$t] ?? $t) : $t; }
    public function UpdateFormField(string $n, string $p, $v): void { $this->fieldUpdates[] = [$n, $p, $v]; }
    public function ReloadForm(): void { $this->reloads++; }
    public function MaintainVariable(string $ident, string $name, int $type, string $profile, int $pos, bool $keep): void
    {
        if ($keep && IPS_GetObjectIDByIdent($ident, $this->InstanceID) === false) {
            $def = [VARIABLETYPE_BOOLEAN => false, VARIABLETYPE_INTEGER => 0, VARIABLETYPE_FLOAT => 0.0, VARIABLETYPE_STRING => ''][$type];
            mkvar($GLOBALS['NEXT_ID']++, $type, $def, $name, $ident, $this->InstanceID);
        }
    }
    public function RegisterVariableString(string $ident, string $name, string $profile, int $pos): int
    {
        $id = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($id === false) { $id = $GLOBALS['NEXT_ID']++; mkvar($id, VARIABLETYPE_STRING, '', $name, $ident, $this->InstanceID); }
        return $id;
    }
    public function EnableAction(string $ident): void { $id = IPS_GetObjectIDByIdent($ident, $this->InstanceID); if ($id !== false) { $GLOBALS['VARS'][$id]['action'] = true; } }
    public function UnregisterVariable(string $ident): void { $id = IPS_GetObjectIDByIdent($ident, $this->InstanceID); if ($id !== false) { unset($GLOBALS['VARS'][$id]); } }
    public function SetValue(string $ident, $v): void { $id = IPS_GetObjectIDByIdent($ident, $this->InstanceID); $GLOBALS['VARS'][$id]['value'] = $v; }
    public function GetValue(string $ident) { $id = IPS_GetObjectIDByIdent($ident, $this->InstanceID); return $GLOBALS['VARS'][$id]['value']; }
    /** Simuliert MC_DeleteModule()+MC_CreateModule(): Attribute weg, Create()/ApplyChanges() laufen neu. */
    public function simulateResync(): void { $this->attrs = []; $this->Create(); $this->ApplyChanges(); }
}

require dirname(__DIR__) . '/DG65Anwesenheitssimulation/module.php';

class AnwsimTest extends DG65Anwesenheitssimulation
{
    public float $unit = 0.5;
    public int $index = 0;
    protected function now(): int { return $GLOBALS['CLOCK']; }
    protected function randomUnit(): float { return $this->unit; }
    protected function randomIndex(int $count): int { return min($this->index, $count - 1); }
    public static function resetLanguage(): void { parent::forgetLanguage(); }
}

$fails = 0;
function check(string $what, bool $ok, string $detail = ''): void
{
    global $fails;
    echo ($ok ? '  ✅ ' : '  ❌ ') . $what . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) { $fails++; }
}
function heading(string $t): void { echo "\n[$t]\n"; }
function ts(string $s): int { return strtotime($s); }
function clock(string $s): void { $GLOBALS['CLOCK'] = ts($s); }
function fire(DG65Anwesenheitssimulation $m, int $vid, $value): void { $GLOBALS['VARS'][$vid]['value'] = $value; $m->MessageSink($GLOBALS['CLOCK'], $vid, VM_UPDATE, [$value, true, null, $GLOBALS['CLOCK']]); }
function bufCount(int $tid): int { $bid = IPS_GetObjectIDByIdent('buf_' . $tid, 12345); return $bid ? count(json_decode(GetValueString($bid), true)) : -1; }
function bufFirst(int $tid): array { $bid = IPS_GetObjectIDByIdent('buf_' . $tid, 12345); $b = json_decode(GetValueString($bid), true); return $b[0] ?? []; }
function actionsFrom(int $n): array { return array_slice($GLOBALS['ACTIONS'], $n); }
function actionsSince(int $from): array { return array_values(array_filter($GLOBALS['ACTIONS'], fn($a) => $a[0] >= $from)); }
/** Rohes Formular, wie das Modul es liefert (statische Texte englisch — Store-Checkliste 25). */
function rawForm(DG65Anwesenheitssimulation $m): array { $f = json_decode($m->GetConfigurationForm(), true); if (!is_array($f)) { throw new RuntimeException('GetConfigurationForm() liefert kein gültiges JSON'); } return $f; }
/** Formular, wie die Konsole es zeigt: sie übersetzt caption/label/suffix/Optionen selbst über locale.json (Store-Checkliste 25). */
function consoleTranslate(array $node): array
{
    foreach (['caption', 'label', 'suffix'] as $k) { if (isset($node[$k]) && is_string($node[$k]) && $GLOBALS['LANG'] === 'de') { $node[$k] = $GLOBALS['DE'][$node[$k]] ?? $node[$k]; } }
    foreach (['items', 'columns', 'options', 'elements', 'actions', 'status'] as $k) { if (isset($node[$k]) && is_array($node[$k])) { foreach ($node[$k] as $i => $child) { if (is_array($child)) { $node[$k][$i] = consoleTranslate($child); } } } }
    foreach (['edit', 'popup'] as $k) { if (isset($node[$k]) && is_array($node[$k])) { $node[$k] = consoleTranslate($node[$k]); } }
    return $node;
}
function form(DG65Anwesenheitssimulation $m): array { return consoleTranslate(rawForm($m)); }
function formText(array $f): string { return json_encode($f, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

// Zielvariablen
mkvar(100, VARIABLETYPE_BOOLEAN, false, 'Wohnzimmerlicht', '', 1, true);
mkvar(101, VARIABLETYPE_BOOLEAN, false, 'Küche', '', 1, true);
mkvar(102, VARIABLETYPE_BOOLEAN, false, 'Probe', '', 1, true);
mkvar(103, VARIABLETYPE_INTEGER, 0, 'Dimmer', '', 1, false);
mkvar(104, VARIABLETYPE_BOOLEAN, false, 'Außenlicht', '', 1, true);
mkvar(200, VARIABLETYPE_BOOLEAN, false, 'Abwesend', '', 1, false);
// Location Control (Sonnenuntergang) — Instanz 10443, Variable Sunset
mkvar(10444, VARIABLETYPE_INTEGER, 0, 'Sonnenuntergang', 'Sunset', 10443);
$GLOBALS['LOC'] = [10443 => true];

$channels = [
    ['Caption' => 'Wohnzimmer', 'TargetID' => 100, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 10],
    ['Caption' => 'Küche',      'TargetID' => 101, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 5],
    ['Caption' => 'Probe',      'TargetID' => 102, 'Mode' => 2, 'TimeRef' => 0, 'JitterMin' => 10],
    ['Caption' => 'Dimmer',     'TargetID' => 103, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0],
    ['Caption' => 'Außenlicht', 'TargetID' => 104, 'Mode' => 1, 'TimeRef' => 1, 'JitterMin' => 10],
];

clock('2026-08-01 12:00:00');
$m = new AnwsimTest();
$m->Create();
$m->props['Channels'] = json_encode($channels);
$m->props['TriggerID'] = 200;
$m->ApplyChanges();

heading('1. Anlage — Status, Nachrichten, Lernspeicher, Timer');
check('Status 102 (Kanäle konfiguriert)', $m->status === 102);
check('Timer aus, solange Simulation aus (Lernen ist ereignisgesteuert)', $m->timers['Tick'] === 0);
check('VM_UPDATE für alle Zielvariablen + Auslöser registriert', isset($m->messages[100][VM_UPDATE], $m->messages[101][VM_UPDATE], $m->messages[102][VM_UPDATE], $m->messages[103][VM_UPDATE], $m->messages[104][VM_UPDATE], $m->messages[200][VM_UPDATE]));
check('Lernspeicher je Kanal angelegt und versteckt', IPS_GetObjectIDByIdent('buf_100', 12345) !== false && $GLOBALS['VARS'][IPS_GetObjectIDByIdent('buf_100', 12345)]['hidden'] === true);
check('Instanzvariable „Simulation aktiv" mit Aktion', IPS_GetObjectIDByIdent('Active', 12345) !== false && HasAction(IPS_GetObjectIDByIdent('Active', 12345)));

heading('2. Vier Lernwochen (10.08.–06.09.2026), plus ein Alt-Ereignis vom 05.08.');
clock('2026-08-05 18:00:00'); fire($m, 100, true);
clock('2026-08-05 23:00:00'); fire($m, 100, false);
$sunsetOf = function (string $day): int { $i = (int)((ts($day) - ts('2026-08-10')) / 86400); return ts($day . ' 20:30:00') - $i * 120; }; // -2 min/Tag
for ($i = 0; $i < 28; $i++) {
    $day = date('Y-m-d', ts('2026-08-10 +' . $i . ' days'));
    $n = (int)date('N', ts($day));
    if ($n >= 6) { clock($day . ' 12:00:00'); fire($m, 101, true); clock($day . ' 13:00:00'); fire($m, 101, false); }
    clock($day . ' ' . sprintf('18:%02d:00', ($i % 4) * 3)); fire($m, 100, true);
    clock($day . ' 18:10:00'); fire($m, 101, true);
    clock($day . ' 19:00:00'); fire($m, 101, false);
    clock($day . ' 20:00:00'); fire($m, 102, true);
    clock($day . ' 21:00:00'); fire($m, 102, false);
    // Außenlicht: 30 min nach dem (wandernden) Sonnenuntergang an, 23:00 aus.
    $GLOBALS['VARS'][10444]['value'] = $sunsetOf($day);
    clock(date('Y-m-d H:i:s', $sunsetOf($day) + 1800)); fire($m, 104, true);
    clock($day . ' 23:00:00'); fire($m, 104, false);
    clock($day . ' 23:30:00'); fire($m, 100, false);
}
check('Wohnzimmer: 56 Schaltvorgänge (2/Tag × 28)', bufCount(100) === 56, 'ist ' . bufCount(100));
check('Küche: 72 (Wochenende 4/Tag)', bufCount(101) === 72, 'ist ' . bufCount(101));
check('Probe (nur lernen) lernt trotzdem mit: 56', bufCount(102) === 56, 'ist ' . bufCount(102));
check('Alt-Ereignis vom 05.08. ist aus dem 28-Tage-Fenster gefallen', date('Y-m-d', bufFirst(100)['t']) === '2026-08-10', 'erstes ' . date('Y-m-d', bufFirst(100)['t']));
check('Sonnenuntergangs-Anker („ss") liegt in jedem Ereignis', isset(bufFirst(104)['ss']));
$before = bufCount(100);
clock('2026-09-06 23:40:00'); fire($m, 100, false);
check('Schreibvorgang ohne Wertänderung wird nicht gelernt', bufCount(100) === $before);
clock('2026-09-01 19:00:00'); fire($m, 103, 30);
clock('2026-09-01 19:00:04'); fire($m, 103, 60);
clock('2026-09-01 19:00:09'); fire($m, 103, 100);
clock('2026-09-01 20:00:00'); fire($m, 103, 0);
check('Dimmer-Schieber (3 Werte in 9 s) = EIN Schaltvorgang mit Endwert 100, dann Aus → 2 Ereignisse', bufCount(103) === 2 && bufFirst(103)['v'] === 100, 'ist ' . bufCount(103) . ' / ' . json_encode(bufFirst(103)));
clock('2026-09-01 20:00:05'); fire($m, 103, 100);
clock('2026-09-01 20:00:08'); fire($m, 103, 0);
check('Hin und zurück binnen Sekunden = nichts passiert (bleibt bei 2)', bufCount(103) === 2, 'ist ' . bufCount(103));

heading('3. Start am Montag 07.09. um 17:00 — Plan, Einschwingen');
$m->unit = 0.5; $m->index = 0;
clock('2026-09-07 17:00:00');
$GLOBALS['VARS'][10444]['value'] = ts('2026-09-08 19:55:00'); // Location Control zeigt schon auf MORGEN
$startIdx = count($GLOBALS['ACTIONS']);
$r = $m->SetActive(true);
check('SetActive(true) meldet ▶️ mit Vorbild-Zeile je Kanal', str_starts_with($r, '▶️') && str_contains($r, 'Wohnzimmer') && str_contains($r, 'Vorbild'));
check('Timer läuft (auf die nächste Schaltzeit gestellt)', $m->timers['Tick'] > 0);
$plan = json_decode($m->attrs['ReplayPlan'], true);
check('Plan für heute angelegt', ($plan['day'] ?? '') === '2026-09-07');
check('Gemeinsamer Vorbild-Tag: Wohnzimmer und Küche = Mo 10.08.', ($plan['channels'][100]['refDate'] ?? '') === '2026-08-10' && ($plan['channels'][101]['refDate'] ?? '') === '2026-08-10', json_encode($plan['channels'] ?? []));
check('Dimmer (erst ab 01.09. gelernt) bekommt eigenen Vorbild-Tag 01.09.', ($plan['channels'][103]['refDate'] ?? '') === '2026-09-01', $plan['channels'][103]['refDate'] ?? '-');
check('Versatz Wohnzimmer +5 min, Küche +2,5 min (gleicher Faktor 0,5)', ($plan['channels'][100]['jitterSec'] ?? 0) === 300 && ($plan['channels'][101]['jitterSec'] ?? 0) === 150);
check('Probe (nur lernen) steht NICHT im Plan', !isset($plan['channels'][102]));
check('Einschwingen um 17:00: nichts fällig, keine Schaltung', count(actionsSince(ts('2026-09-07 17:00:00'))) === 0, json_encode(actionsSince(ts('2026-09-07 17:00:00'))));
check('NextAction zeigt die nächste Schaltung 18:05 Wohnzimmer → An', str_contains($m->GetValue('NextAction'), '18:05') && str_contains($m->GetValue('NextAction'), 'Wohnzimmer'), $m->GetValue('NextAction'));
$b = bufCount(100);
fire($m, 100, true);
check('Während der Simulation wird NICHT gelernt', bufCount(100) === $b);
$GLOBALS['VARS'][100]['value'] = false;

heading('4. Minütliche Wiedergabe bis Mitternacht');
for ($t = ts('2026-09-07 17:01:00'); $t <= ts('2026-09-07 23:59:00'); $t += 60) {
    $GLOBALS['CLOCK'] = $t;
    $m->Tick();
}
$acts = array_map(fn($a) => date('H:i', $a[0]) . ' #' . $a[1] . '=' . var_export($a[2], true) . ' ' . $a[3], actionsSince(ts('2026-09-07 17:00:00')));
// Dimmer: koaleszierte Ereignisse tragen den Zeitstempel des LETZTEN Werts
// (19:00:09 / 20:00:08) → fällig beim nächsten vollen Minuten-Tick (19:01/20:01).
// Außenlicht (Sonnenuntergang): ALLE Ereignisse relativ zum Sonnenstand — am
// Vorbild-Tag Untergang 20:30, An 21:00 (+30 min), Aus 23:00 (+2:30 h); heute
// Untergang 19:55 → An 20:25, Aus 22:25, je +5 min Versatz.
$expected = ['18:05 #100=true RequestAction', '18:13 #101=true RequestAction', '19:03 #101=false RequestAction', '19:01 #103=100 SetValue', '20:01 #103=0 SetValue', '20:30 #104=true RequestAction', '22:30 #104=false RequestAction', '23:35 #100=false RequestAction'];
sort($acts); $exp = $expected; sort($exp);
check('Schaltfolge exakt wie erwartet (Uhrzeit + Versatz, Sonnenuntergangs-Abstand, Dimmer per SetValue ohne Aktion)', $acts === $exp, "\n      ist:  " . implode(' | ', $acts) . "\n      soll: " . implode(' | ', $exp));
check('Jede Schaltung genau einmal', count($acts) === count($expected));
check('LastAction nennt die letzte Schaltung mit Vorbild-Tag (TT.MM.JJJJ, Regel 9b)', str_contains($m->GetValue('LastAction'), 'Wohnzimmer → Aus') && str_contains($m->GetValue('LastAction'), 'Vorbild Mo 10.08.2026'), $m->GetValue('LastAction'));
check('Kein Maschinen-Datum JJJJ-MM-TT in sichtbaren Texten (LastAction/NextAction/Debug)', !preg_match('/\d{4}-\d{2}-\d{2}/', $m->GetValue('LastAction') . $m->GetValue('NextAction') . implode(' ', $m->debug)), implode(' | ', array_slice($m->debug, -3)));
check('NextAction: heute nichts mehr', str_contains($m->GetValue('NextAction'), 'keine weitere'), $m->GetValue('NextAction'));
check('Kein Fehler-Log', count($GLOBALS['LOG']) === 0, implode(' | ', $GLOBALS['LOG']));

heading('5. Tageswechsel — neuer Vorbild-Tag (Dienstag), Einschwingen');
$GLOBALS['VARS'][10444]['value'] = ts('2026-09-08 19:53:00');
clock('2026-09-08 00:00:00'); $m->Tick();
$plan = json_decode($m->attrs['ReplayPlan'], true);
check('Plan für 08.09. mit Vorbild Di 11.08.', ($plan['day'] ?? '') === '2026-09-08' && ($plan['channels'][100]['refDate'] ?? '') === '2026-08-11', json_encode($plan['channels'][100] ?? []));
check('Einschwingen um Mitternacht: Wohnzimmer war um 0:00 am Vorbild aus → keine Schaltung nötig (Ist = Aus)', count(actionsSince(ts('2026-09-08 00:00:00'))) === 0, json_encode(actionsSince(ts('2026-09-08 00:00:00'))));
$GLOBALS['VARS'][100]['value'] = true; // jemand hat es „von Hand" angelassen
clock('2026-09-08 00:01:00'); $m->attrs['ReplayPlan'] = '{}'; $m->Tick();
$a = actionsSince(ts('2026-09-08 00:01:00'));
check('Einschwingen stellt den Vorbild-Zustand her: Wohnzimmer An → Aus', count($a) === 1 && $a[0][1] === 100 && $a[0][2] === false, json_encode($a));

heading('6. Beenden per RequestAction(Active=false)');
$m->RequestAction('Active', false);
check('Timer aus, Plan geleert, NextAction leer', $m->timers['Tick'] === 0 && $m->attrs['ReplayPlan'] === '{}' && $m->GetValue('NextAction') === '');
check('SetActive(false) im Aus-Zustand: ℹ️', str_starts_with($m->SetActive(false), 'ℹ️'));
clock('2026-09-08 07:00:00'); fire($m, 100, false); fire($m, 100, true);
$bid = IPS_GetObjectIDByIdent('buf_100', 12345); $buf = json_decode(GetValueString($bid), true); $lastEv = end($buf);
check('Lernen läuft wieder: letztes Ereignis = An um 07:00 (das Alt-„Aus" wurde dedupliziert)', $lastEv['v'] === true && $lastEv['t'] === ts('2026-09-08 07:00:00'), json_encode($lastEv));
// Die Simulation lief vom 07.09. 17:00 bis 08.09. 00:01 (gut 7 h) — diese Zeit zählt nicht zum Lernfenster,
// deshalb bleibt der 10.08. noch drin (ohne Gutschrift wäre er herausgefallen).
check('Lernfenster: Simulationszeit zählt nicht mit — ältestes Ereignis weiter vom 10.08.', date('Y-m-d', bufFirst(100)['t']) === '2026-08-10', date('Y-m-d', bufFirst(100)['t']));
$GLOBALS['VARS'][100]['value'] = false;

heading('7. Auslöser-Variable');
clock('2026-09-08 08:00:00');
fire($m, 200, true);
check('Auslöser TRUE → Simulation an', $m->GetValue('Active') === true && $m->timers['Tick'] > 0);
fire($m, 200, false);
check('Auslöser FALSE → Simulation aus', $m->GetValue('Active') === false);
$m->props['TriggerInvert'] = true; $m->ApplyChanges();
fire($m, 200, false);
check('Invertiert: FALSE („Anwesend"=nein) → Simulation an', $m->GetValue('Active') === true);
fire($m, 200, true);
check('Invertiert: TRUE → aus', $m->GetValue('Active') === false);
$m->props['TriggerInvert'] = false; $m->ApplyChanges();

heading('8. Vorschau, Vergessen, Kanal ohne Daten');
$m->index = 2;
$p = $m->Preview();
check('Preview bei ausgeschalteter Simulation: probeweise, mit Kanalnamen und Zeiten', str_contains($p, 'probeweise') && str_contains($p, 'Wohnzimmer') && preg_match('/\d\d:\d\d An/', $p) === 1, $p);
check('Preview verändert den gespeicherten Plan nicht', $m->attrs['ReplayPlan'] === '{}');
check('ForgetChannel(unbekannt) → ⚠️', str_starts_with($m->ForgetChannel('Gibtsnicht'), '⚠️'));
check('ForgetChannel(Probe) leert den Speicher', str_starts_with($m->ForgetChannel('Probe'), '🗑') && bufCount(102) === 0);
$m->props['Channels'] = json_encode([['Caption' => 'Leer', 'TargetID' => 102, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 5]]);
$m->ApplyChanges();
clock('2026-09-08 09:00:00');
$r = $m->SetActive(true);
check('Start mit Kanal ohne Gelerntes: startet, meldet „bleibt still"', str_contains($r, 'bleibt still'), $r);
$m->Tick();
check('… und schaltet nichts', count(actionsSince(ts('2026-09-08 09:00:00'))) === 0);
$m->RequestAction('Active', false);
$m->props['Channels'] = json_encode([]);
$m->ApplyChanges();
check('Ohne Kanäle: Status 104, verwaiste Lernspeicher gelöscht', $m->status === 104 && IPS_GetObjectIDByIdent('buf_100', 12345) === false);
check('SetActive(true) ohne abspielbare Kanäle → ⚠️', str_starts_with($m->SetActive(true), '⚠️'));
$m->props['Channels'] = json_encode($channels);
$m->ApplyChanges();

heading('9. Resync (Attribut-Verlust) — Gelerntes bleibt, Simulation läuft weiter');
clock('2026-09-08 10:00:00'); fire($m, 101, true);
clock('2026-09-08 11:00:00'); fire($m, 101, false);
$b = bufCount(101);
$m->RequestAction('Active', true);
$m->simulateResync();
check('Lernspeicher überlebt den Resync (Variable, kein Attribut)', bufCount(101) === $b);
check('Aktive Simulation: Timer nach Resync wieder an, nächster Tick baut neuen Plan', $m->timers['Tick'] > 0);
clock('2026-09-08 10:01:00'); $m->Tick();
check('Neuer Plan nach Resync', (json_decode($m->attrs['ReplayPlan'], true)['day'] ?? '') === '2026-09-08');
$m->RequestAction('Active', false);

heading('10. Formular — Konvention und Verdrahtung');
$f = form($m);
check('Zweck-Intro ganz vorn', ($f['elements'][0]['name'] ?? '') === 'PurposeIntroPanel');
check('News-Panel an zweiter Stelle', ($f['elements'][1]['name'] ?? '') === 'NewsPanel');
check('Doku-Panel an dritter Stelle', str_contains($f['elements'][2]['caption'] ?? '', 'Dokumentation'));
check('Simulation-Panel, dann Kanäle', str_contains($f['elements'][3]['caption'] ?? '', 'Simulation') && str_contains($f['elements'][4]['caption'] ?? '', 'Kanäle'));
check('Lizenz-Panel ganz unten', str_contains(end($f['elements'])['caption'] ?? '', 'Über dieses Modul'));
check('Status-Codes 102/104 beschriftet', count($f['status'] ?? []) === 2);
$txt = formText($f);
check('Astro-Status sichtbar (✅, da Location Control im Test vorhanden)', str_contains($txt, 'Sonnenauf-/-untergang verfügbar'));
check('Kanal-Zusammenfassung nennt Schaltvorgänge', str_contains($txt, 'Schaltvorgänge seit'));
check('Kanalliste, Vorschlagsliste und Meldeziele haben je genau eine auto-Spalte', substr_count($txt, '"width":"auto"') === 3);
check('Dritte Untergruppe „Meldungen" im Panel Simulation, beide Meldungen in der Vorgabe aus', str_contains($txt, '🔔  Meldungen') && $m->props['NotifyFailure'] === false && $m->props['NotifyStartStop'] === false);
check('Weiterhin nur zwei Fachpanels auf oberster Ebene (kein „Formular: Aufbau" nötig), Untergruppen darin', count($f['elements']) === 7 && str_contains($txt, '▶️  Start und Ende') && str_contains($txt, '🧠  Lernen und Vorbild-Tag'), (string)count($f['elements']));
check('Feiertags-Auswahl: 16 Bundesländer + Österreich + Schweiz + „nicht berücksichtigen", von der Konsole übersetzt', substr_count($txt, '"value":"') >= 18 && str_contains($txt, '"caption":"Bayern","value":"BY"') && str_contains($txt, '"caption":"Feiertage nicht berücksichtigen","value":""'));
check('Kein Maschinen-Datum JJJJ-MM-TT im Formular (Regel 9b)', !preg_match('/\d{4}-\d{2}-\d{2}/', $txt));
check('Keine Ersatz-Umlaute (ue/ae/oe) in sichtbaren Formulartexten', !preg_match('/\b(fuer|ueber|zurueck|aendern|loeschen|Rueck)/i', $txt));
check('Kein Link-Button trägt die URL direkt in "link"', !preg_match('/"link":"http/', $txt));
check('Panel „Rückmeldungen" und Doku verlinken den Forum-Thread 144973', substr_count($txt, 'community.symcon.de/t/144973') === 2 && str_contains($txt, 'Zum Forum-Thread') && str_contains($txt, 'eigenen Thread im Symcon-Forum'));
// Wer den Hinweis vor 0.3.1 weggeklickt hat (als es noch keinen Thread gab), sieht ihn mit dem Link einmal wieder.
$m->attrs['ForumHintGone'] = true;
check('Alt-Bestand „weggeklickt": Hinweis mit dem neuen Link erscheint einmal wieder', count(array_filter(form($m)['elements'], fn($e) => ($e['name'] ?? '') === 'ForumHintPanel')) === 1);
$m->AckPurposeIntro(); $m->AckNews(); $m->AckForumHint();
check('Bestätigen merkt sich den Stand des Hinweises (thread-144973)', $m->attrs['ForumHintSeen'] === 'thread-144973' && count(array_filter(form($m)['elements'], fn($e) => ($e['name'] ?? '') === 'ForumHintPanel')) === 0);
$f2 = form($m);
check('Nach Bestätigen: Intro/News/Forum weg, Lizenz bleibt', count($f2['elements']) === count($f['elements']) - 3 && str_contains(end($f2['elements'])['caption'], 'Über dieses Modul'));
// News-Panel nach NEWS_VERSIONS-Muster (SUITE.md Formular-Optik Punkt 1)
$libVer = json_decode(file_get_contents(dirname(__DIR__) . '/library.json'), true)['version'];
check('AckNews speichert die installierte Version ohne Beta-Suffix', $m->attrs['SeenNews'] === $libVer, $m->attrs['SeenNews']);
check('Höchster NEWS_VERSIONS-Eintrag = library.json-Version (Pflege-Pflicht)', str_contains(formText($f), 'Neu bis Version ' . $libVer), $f['elements'][1]['caption'] ?? '');
check('Frisch installiert: alle Versionen im Banner (0.1.0 und ' . $libVer . ')', str_contains(formText($f), 'Version 0.1.0:') && str_contains(formText($f), 'Version ' . $libVer . ':'));
$m->attrs['SeenNews'] = '0.1.0';
$f3 = form($m);
$hasNews = fn(array $f) => count(array_filter($f['elements'], fn($e) => ($e['name'] ?? '') === 'NewsPanel')) === 1;
check('Nach Update von 0.1.0: Banner zeigt nur die Lücke (0.1.1+), nicht 0.1.0', $hasNews($f3) && !str_contains(formText($f3), 'Version 0.1.0:') && str_contains(formText($f3), 'Version 0.1.1:'), formText($f3));
$m->attrs['SeenNews'] = '9.9.9';
check('Nichts Neueres als SeenNews: kein Banner', !$hasNews(form($m)));
$m->attrs['SeenNews'] = $libVer;
$GLOBALS['LOC'] = [];
check('Ohne Location Control: ℹ️-Rückfall-Hinweis statt ✅', str_contains(formText(form($m)), 'Keine Location-Control-Instanz'));
$GLOBALS['LOC'] = [10443 => true];

heading('11. Vertrags-Hygiene (SUITE.md Stolperstein 8/20) und onClick-Verdrahtung');
$rc = new ReflectionClass(DG65Anwesenheitssimulation::class);
foreach (['SetActive', 'Preview', 'ForgetChannel', 'ImportArchive', 'Tick', 'AckPurposeIntro', 'AckNews', 'AckForumHint'] as $name) {
    $rm = $rc->getMethod($name);
    $okTypes = true; $okDefaults = true;
    foreach ($rm->getParameters() as $p) {
        $t = $p->getType();
        if ($t === null || !in_array($t->getName(), ['bool', 'int', 'float', 'string'], true)) { $okTypes = false; }
        if ($p->isOptional()) { $okDefaults = false; }
    }
    check("$name(): Parameter skalar typisiert, keine PHP-Standardwerte", $okTypes && $okDefaults);
}
preg_match_all('/ANWSIM_([A-Za-z]+)\(/', $txt, $mm);
foreach (array_unique($mm[1]) as $fn) {
    check("ANWSIM_$fn → public function $fn() existiert", $rc->hasMethod($fn) && $rc->getMethod($fn)->isPublic());
}
check('Kern-Aktionen sind im Formular verdrahtet', count(array_diff(['SetActive', 'Preview'], array_unique($mm[1]))) === 0);
check('Kein IPS_LogMessage( in module.php (SUITE.md Store-Review Punkt 15, nur $this->LogMessage)', strpos(file_get_contents(dirname(__DIR__) . '/DG65Anwesenheitssimulation/module.php'), 'IPS_LogMessage(') === false);

heading('13. Symcon-Start und fehlende Zielvariable: Lernspeicher nie vorschnell löschen (SUITE.md Store-Review Punkt 17)');
$m->props['Channels'] = json_encode($channels);
$m->ApplyChanges();
clock('2026-09-08 12:00:00'); fire($m, 101, true);
$bufBefore = bufCount(101);
check('Vorbereitung: Küche hat Gelerntes', $bufBefore > 0, (string)$bufBefore);
// (a) ApplyChanges VOR KR_READY: nichts anfassen, nur auf Kernel-Nachricht warten
$GLOBALS['RUNLEVEL'] = KR_INIT;
$m->messages = [];
$m->ApplyChanges();
check('Vor KR_READY: nur IPS_KERNELMESSAGE (Sender 0) registriert, keine VM_UPDATE-Registrierung', isset($m->messages[0][IPS_KERNELMESSAGE]) && count($m->messages) === 1, json_encode(array_keys($m->messages)));
check('Vor KR_READY: alle Lernspeicher unangetastet', bufCount(101) === $bufBefore && bufCount(100) >= 0 && IPS_GetObjectIDByIdent('buf_104', 12345) !== false);
$GLOBALS['RUNLEVEL'] = KR_READY;
$m->MessageSink($GLOBALS['CLOCK'], 0, IPS_KERNELMESSAGE, [KR_READY]);
check('KR_READY-Nachricht holt ApplyChanges nach: VM_UPDATE-Registrierungen da', isset($m->messages[101][VM_UPDATE]) && isset($m->messages[200][VM_UPDATE]));
// (b) Zielvariable vorübergehend weg (Fremdmodul legt neu an): Puffer bleibt, Kanal pausiert
$saved = $GLOBALS['VARS'][101]; unset($GLOBALS['VARS'][101]);
$m->ApplyChanges();
check('Zielvariable fehlt: Lernspeicher bleibt erhalten', IPS_GetObjectIDByIdent('buf_101', 12345) !== false && bufCount(101) === $bufBefore, (string)bufCount(101));
check('Zielvariable fehlt: keine VM_UPDATE-Registrierung auf die tote ID, Status bleibt 102 (andere Kanäle laufen)', !isset($m->messages[101]) && $m->status === 102);
$GLOBALS['VARS'][101] = $saved;
$m->ApplyChanges();
check('Zielvariable wieder da: Kanal lernt weiter mit altem Gelernten', isset($m->messages[101][VM_UPDATE]) && bufCount(101) === $bufBefore);
// (c) Kanal wirklich aus der Liste entfernt → Puffer weg
$m->props['Channels'] = json_encode(array_values(array_filter($channels, fn($c) => $c['TargetID'] !== 101)));
$m->ApplyChanges();
check('Kanal aus Liste entfernt: Lernspeicher gelöscht', IPS_GetObjectIDByIdent('buf_101', 12345) === false);
$m->props['Channels'] = json_encode($channels);
$m->ApplyChanges();
check('Keine Presentation-OPTIONS im Modul (Farbschlüssel-Falle SUITE.md Stolperstein 9 kann nicht greifen)', !preg_match('/PRESENTATION|OPTIONS|ColorValue|ColorActive/', file_get_contents(dirname(__DIR__) . '/DG65Anwesenheitssimulation/module.php')));

heading('16. Store-Review: Objekteigenschaften nur vorbelegen (Sichtbarkeit der Lernspeicher gehört dem Nutzer)');
$m->props['Channels'] = json_encode($channels); $m->ApplyChanges();
$hb = IPS_GetObjectIDByIdent('buf_100', 12345);
check('Lernspeicher ist nach der Anlage versteckt', $GLOBALS['VARS'][$hb]['hidden'] === true);
$GLOBALS['VARS'][$hb]['hidden'] = false; // Nutzer blendet ihn ein
$m->ApplyChanges();
check('Nach erneutem Übernehmen bleibt er eingeblendet (kein erzwungenes Verstecken)', $GLOBALS['VARS'][$hb]['hidden'] === false);
$GLOBALS['VARS'][$hb]['hidden'] = true;
check('Kein IPSLight/Bordmittel-Vergleich mehr in den READMEs (die Richtigstellung steht nur im CHANGELOG)', !preg_match('/IPSLight|Bordmittel|built into Symcon/', file_get_contents(dirname(__DIR__) . '/README.md') . file_get_contents(dirname(__DIR__) . '/README.en.md')));

heading('14. Lernfenster gilt auch beim Lesen (Kanal ohne neue Ereignisse, Live-Fund 23.10.2026)');
$m->props['Channels'] = json_encode($channels);
$m->ApplyChanges();
clock('2026-09-08 12:00:00'); fire($m, 104, true); clock('2026-09-08 13:00:00'); fire($m, 104, false);
$rawTerrasse = count(json_decode(GetValueString(IPS_GetObjectIDByIdent('buf_104', 12345)), true));
check('Vorbereitung: Außenlicht hat Roh-Ereignisse vom 08.09. im Speicher', $rawTerrasse === 2, (string)$rawTerrasse);
clock('2026-09-30 12:00:00');
check('Innerhalb des Fensters (30.09., Fenster ab 02.09.): Außenlicht hat ein Vorbild', preg_match('/• Außenlicht \(Vorbild/', $m->Preview()) === 1, $m->Preview());
clock('2026-10-20 12:00:00'); // Fenster ab 22.09. — Terrasse hat seitdem nichts mehr gelernt
$p = $m->Preview();
check('Preview: Außenlicht „noch nichts gelernt" statt veraltetem Vorbild-Tag', preg_match('/• Außenlicht.*noch nichts gelernt/', $p) === 1, $p);
$f = form($m); $sum = ''; $walk = function ($items) use (&$walk, &$sum) { foreach ($items as $e) { if (($e['name'] ?? '') === 'ChannelSummary') { $sum = $e['caption']; } if (isset($e['items'])) { $walk($e['items']); } } }; $walk($f['elements']);
check('Kanal-Zusammenfassung zählt für Außenlicht nichts Veraltetes', str_contains($sum, 'Außenlicht: noch nichts gelernt'), $sum);
check('Rohspeicher bleibt unangetastet (nur lesend getrimmt, Lernen räumt später auf)', count(json_decode(GetValueString(IPS_GetObjectIDByIdent('buf_104', 12345)), true)) === $rawTerrasse);
clock('2026-09-08 12:00:00');

heading('15. Zweisprachig — Englisch ohne deutsche Reste, Deutsch wortgleich, Tabelle vollständig (SUITE.md Punkt 9, Stolperstein 24)');
// (a) Stolperstein 24: Translate() wird je PHP-Lauf nur für den Probe-Text gefragt, nie je Text.
check('Translate() wurde bisher genau einmal gefragt (Probe-Text), Tabelle selbst gelesen', ($GLOBALS['TRANSLATE_CALLS'] ?? 0) === 1, (string)($GLOBALS['TRANSLATE_CALLS'] ?? 0));
// (a2) Store-Checkliste 25: statische Formulartexte NICHT selbst übersetzen — die Konsole tut das.
$raw = rawForm($m); $rawTxt = formText($raw);
check('Rohes Formular (deutsches Symcon): statische Captions bleiben englisch, Konsole übersetzt', str_contains($rawTxt, '"caption":"📖  Documentation & help"') && str_contains($rawTxt, '"caption":"Channels"') && ($raw['status'][0]['caption'] ?? '') === 'Active — channels configured, the module is learning or replaying', mb_substr($rawTxt, 0, 160));
$rawSum = ''; $wk = function ($items) use (&$wk, &$rawSum) { foreach ($items as $e) { if (($e['name'] ?? '') === 'ChannelSummary') { $rawSum = $e['caption']; } if (isset($e['items'])) { $wk($e['items']); } } }; $wk($raw['elements']);
check('Rohes Formular: Statuszeilen MIT Werten sind selbst übersetzt (Konsole könnte sie nicht)', str_contains($rawSum, 'Schaltvorgänge seit') || str_contains($rawSum, 'noch nichts gelernt'), $rawSum);
check('Statische Formulartexte: jeder Caption-Text steht in locale.json (sonst bliebe er in der Konsole englisch)', (function () use ($raw) { $miss = []; $w = function ($node) use (&$w, &$miss) { foreach (['caption', 'suffix'] as $k) { if (isset($node[$k]) && is_string($node[$k]) && $node[$k] !== '' && !isset($GLOBALS['DE'][$node[$k]]) && preg_match('/[a-z]{3,}/', $node[$k]) && !preg_match('/\d\d\.\d\d\.\d{4}|ANWSIM_|Version |switching event|channel\(s\)/', $node[$k]) && !in_array($node['name'] ?? '', ['SimStatus', 'ChannelSummary', 'TriggerStatus', 'NotifyStatus', 'BrightnessStatus'], true)) { $miss[] = $node[$k]; } } foreach (['items', 'columns', 'options', 'elements', 'status'] as $k) { if (isset($node[$k])) { foreach ($node[$k] as $c) { if (is_array($c)) { $w($c); } } } } foreach (['edit', 'popup'] as $k) { if (isset($node[$k])) { $w($node[$k]); } } }; $w($raw); $GLOBALS['MISS'] = $miss; return $miss === []; })(), implode(' | ', array_slice($GLOBALS['MISS'] ?? [], 0, 5)));
// (b) Statisch: jeder t('…')-Text im Code steht in locale.json, Platzhalter stimmen, keine Umlaute in Code-Zeichenketten.
$src = file_get_contents(dirname(__DIR__) . '/DG65Anwesenheitssimulation/module.php');
$toks = token_get_all($src);
$tTexts = []; $umlautStrings = [];
$n = count($toks);
for ($i = 0; $i < $n; $i++) {
    if (is_array($toks[$i]) && $toks[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
        $raw = $toks[$i][1];
        $val = $raw[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($raw, 1, -1)) : stripcslashes(substr($raw, 1, -1));
        // Ausnahmen sind Daten, keine Oberflächentexte: die Geräte-Wortliste (Vorgabe von BlockedWords) und der Eigenname Baden-Württemberg.
        if (preg_match('/[äöüÄÖÜß„“]/u', $val) && !str_starts_with($val, 'Toaster, ') && $val !== 'Baden-Württemberg' && !str_starts_with($val, '/')) { $umlautStrings[] = mb_substr($val, 0, 40); }
        // t('…' direkt davor?
        $j = $i - 1; while ($j > 0 && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) { $j--; }
        if (($toks[$j] ?? '') === '(' ) { $k = $j - 1; if (is_array($toks[$k]) && $toks[$k][1] === 't' && ($toks[$k - 1] ?? '') !== '' && is_array($toks[$k - 1]) && $toks[$k - 1][0] === T_OBJECT_OPERATOR) { $tTexts[] = $val; } }
    }
}
$rcN = new ReflectionClass(DG65Anwesenheitssimulation::class);
foreach ($rcN->getConstant('NEWS_VERSIONS') as $lines) { foreach ($lines as $l) { $tTexts[] = $l; } }
foreach (json_decode(file_get_contents(dirname(__DIR__) . '/DG65Anwesenheitssimulation/form.json'), true)['status'] as $s) { $tTexts[] = $s['caption']; }
$tTexts = array_values(array_unique($tTexts));
$missing = array_values(array_filter($tTexts, fn($t) => !isset($GLOBALS['DE'][$t])));
check('Alle ' . count($tTexts) . ' Quelltexte (t(), NEWS_VERSIONS, form.json-Status) haben eine deutsche Fassung', $missing === [], implode(' | ', array_slice($missing, 0, 5)) . ' (' . count($missing) . ' fehlen)');
$ph = fn(string $s) => preg_match_all('/%(\d+\$)?[sd]/', str_replace('%%', '', $s));
$phBad = array_values(array_filter($tTexts, fn($t) => isset($GLOBALS['DE'][$t]) && $ph($t) !== $ph($GLOBALS['DE'][$t])));
check('Platzhalter (%s/%1$s) in Englisch und Deutsch gleich', $phBad === [], implode(' | ', $phBad));
check('Keine deutschen Umlaute/Anführungszeichen in Code-Zeichenketten (nur in locale.json)', $umlautStrings === [], implode(' | ', $umlautStrings));
check('Mehr als 40 t()-Quelltexte erfasst (Erkennung greift; statische Captions prüft a2)', count($tTexts) > 40, (string)count($tTexts));
// (c) Englisch: Formular, Vorschau und Rückmeldungen ohne deutsche Reste (Kanalnamen sind Nutzerdaten → englische Fixture).
$GLOBALS['LANG'] = 'en'; AnwsimTest::resetLanguage();
$enChannels = [['Caption' => 'Living room', 'TargetID' => 100, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 10], ['Caption' => 'Kitchen', 'TargetID' => 101, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 5], ['Caption' => 'Patio', 'TargetID' => 104, 'Mode' => 1, 'TimeRef' => 1, 'JitterMin' => 10], ['Caption' => 'Probe', 'TargetID' => 102, 'Mode' => 2, 'TimeRef' => 0, 'JitterMin' => 10]];
$m->props['Channels'] = json_encode($enChannels); $m->ApplyChanges();
clock('2026-09-08 12:00:00'); fire($m, 101, true); clock('2026-09-08 12:30:00'); fire($m, 101, false);
clock('2026-09-09 13:00:00'); $m->index = 0;
$german = '/[äöüÄÖÜß„]|\b(und|nicht|noch|Kanal|Kanäle|Vorbild|Uhr|Versatz|Schalt\w*|Lern\w*|Simulation aktiv|Zielvariable|gelernt|Aus|An)\b/u';
$fEn = rawForm($m); $fEnTxt = str_replace('Baden-Württemberg', '', formText($fEn)); // Eigenname, auch im Englischen
$germanHits = []; preg_match_all($german, $fEnTxt, $gm); $germanHits = array_unique($gm[0]);
check('Englisches Formular ohne deutsche Reste', $germanHits === [], implode(' | ', array_slice($germanHits, 0, 10)));
check('Englisches Formular: Panels heißen englisch', str_contains($fEnTxt, 'Documentation & help') && str_contains($fEnTxt, 'About this module') && str_contains($fEnTxt, '"caption":"Channels"'), mb_substr($fEnTxt, 0, 200));
check('Englischer Status-Text aus form.json übersetzt durchgereicht', ($fEn['status'][0]['caption'] ?? '') === 'Active — channels configured, the module is learning or replaying');
$pEn = $m->Preview();
check('Englische Vorschau: reference day, offset, On/Off, keine deutschen Reste', str_contains($pEn, 'reference day') && str_contains($pEn, 'offset') && preg_match('/\d\d:\d\d On/', $pEn) === 1 && !preg_match($german, $pEn), $pEn);
$rEn = $m->SetActive(true);
check('Englische Start-Meldung', str_starts_with($rEn, '▶️ Simulation started.') && str_contains($rEn, 'Kitchen: reference day Tue 08.09.2026') && !preg_match($german, $rEn), $rEn);
check('Englische Variablen „Next switching" ohne „Uhr"', !str_contains($m->GetValue('NextAction'), 'Uhr') && (str_contains($m->GetValue('NextAction'), 'No further switching') || preg_match('/^\d\d:\d\d: /', $m->GetValue('NextAction')) === 1), $m->GetValue('NextAction'));
check('Englische Stopp-Meldung', str_starts_with($m->SetActive(false), '⏹ Simulation stopped'));
check('Englisch: ForgetChannel-Meldungen', str_starts_with($m->ForgetChannel('Nope'), '⚠️ No channel named "Nope" found.') && str_starts_with($m->ForgetChannel('Probe'), '🗑 Learned data of "Probe" discarded'));
$GLOBALS['FAIL_RA'] = true; $m->index = 0; clock('2026-09-09 12:05:00'); $m->RequestAction('Active', true);
check('Englische Fehlschlag-Meldung im Protokoll und in „Last switching"', str_contains(end($GLOBALS['LOG']), 'Switching failed: Kitchen') && str_contains($m->GetValue('LastAction'), 'failed') && !str_contains($m->GetValue('LastAction'), 'Uhr'), end($GLOBALS['LOG']) . ' / ' . $m->GetValue('LastAction'));
$GLOBALS['FAIL_RA'] = false; $m->RequestAction('Active', false);
// (d) Zurück auf Deutsch: Wortlaut wie vor der Umstellung.
$GLOBALS['LANG'] = 'de'; AnwsimTest::resetLanguage();
$m->props['Channels'] = json_encode($channels); $m->ApplyChanges();
$fDeTxt = formText(form($m));
check('Deutsch nach Umschalten wieder wortgleich (Panels, Status, Buttons)', str_contains($fDeTxt, '📖  Dokumentation & Hilfe') && str_contains($fDeTxt, '▶️ Simulation jetzt starten') && str_contains($fDeTxt, 'Aktiv — Kanäle konfiguriert'), mb_substr($fDeTxt, 0, 200));
check('Deutsche Vorschau mit „Uhr"-losen Zeiten und „Vorbild"', str_contains($m->Preview(), 'Vorbild'));

heading('17. Lange Abwesenheit, sekundengenaues Schalten, verpasste Schaltungen (0.3.0)');
// Eigene Instanz, damit die Zeiten der übrigen Blöcke nicht hineinspielen.
$mk = function (int $iid, array $chs): AnwsimTest { $x = new AnwsimTest(); $x->InstanceID = $iid; $x->Create(); $x->props['Channels'] = json_encode($chs); $x->ApplyChanges(); return $x; };
$bufOf = fn(int $iid, int $tid) => json_decode(GetValueString(IPS_GetObjectIDByIdent('buf_' . $tid, $iid)), true);
mkvar(300, VARIABLETYPE_BOOLEAN, false, 'Stehlampe', '', 1, true);
clock('2026-06-01 12:00:00');
$m2 = $mk(700, [['Caption' => 'Stehlampe', 'TargetID' => 300, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
// 4 Wochen lernen: 19:00:10 an, 19:00:40 aus (Paar in derselben Minute!), 21:00:00 an, 23:00:00 aus.
for ($i = 0; $i < 28; $i++) { $d = date('Y-m-d', ts('2026-06-01 +' . $i . ' days')); foreach ([['19:00:10', true], ['19:00:40', false], ['21:00:00', true], ['23:00:00', false]] as [$t, $v]) { clock("$d $t"); fire($m2, 300, $v); } }
check('Vorbereitung: 112 Schaltvorgänge gelernt (4/Tag × 28)', count($bufOf(700, 300)) === 112, (string)count($bufOf(700, 300)));
clock('2026-06-29 08:00:00'); $m2->RequestAction('Active', true);
check('Simulationszeit wird gemerkt (offener Eintrag in SimPeriods, Variable versteckt)', (json_decode(GetValueString(IPS_GetObjectIDByIdent('SimPeriods', 700)), true)[0] ?? null) === [ts('2026-06-29 08:00:00'), 0] && $GLOBALS['VARS'][IPS_GetObjectIDByIdent('SimPeriods', 700)]['hidden'] === true);
// Wie Symcon: der Timer läuft ab, dann Tick(). 45 Tage Abwesenheit.
$perDay = []; $ticks = 0; $maxWait = 0; $minWait = PHP_INT_MAX; $day1 = [];
$end = ts('2026-08-13 07:59:00');
while ($GLOBALS['CLOCK'] < $end) {
    $wait = intdiv($m2->timers['Tick'], 1000); $maxWait = max($maxWait, $wait); $minWait = min($minWait, $wait);
    $GLOBALS['CLOCK'] += $wait; $before = count($GLOBALS['ACTIONS']); $m2->Tick(); $ticks++;
    foreach (array_slice($GLOBALS['ACTIONS'], $before) as $a) { $perDay[date('Y-m-d', $a[0])] = ($perDay[date('Y-m-d', $a[0])] ?? 0) + 1; if (date('Y-m-d', $a[0]) === '2026-06-29') { $day1[] = date('H:i:s', $a[0]) . '=' . var_export($a[2], true); } }
}
check('Tag 1 sekundengenau: 19:00:10 an, 19:00:40 aus, 21:00:00 an, 23:00:00 aus (Paar in derselben Minute bleibt sichtbar)', $day1 === ['19:00:10=true', '19:00:40=false', '21:00:00=true', '23:00:00=false'], implode(' ', $day1));
check('Alle 45 Abwesenheitstage haben 4 Schaltungen — auch Tag 29 bis 45 (Lernfenster steht still)', count($perDay) === 45 && min($perDay) === 4 && max($perDay) === 4, count($perDay) . ' Tage, min ' . (min($perDay ?: [0])) . ', max ' . (max($perDay ?: [0])));
check('Timer: nie unter 1 s, nie über 10 min, sparsam (< 250 Durchläufe je Tag)', $minWait >= 1 && $maxWait <= 600 && $ticks / 45 < 250, "min $minWait s, max $maxWait s, " . round($ticks / 45) . ' je Tag');
// Verpasste Schaltungen (Symcon stand 3 h): nur die letzte fällige zählt, kein Durchschalten.
clock('2026-08-13 18:30:00'); $m2->Tick(); $GLOBALS['VARS'][300]['value'] = false; $before = count($GLOBALS['ACTIONS']);
clock('2026-08-13 21:30:00'); $m2->Tick();
$missed = array_slice($GLOBALS['ACTIONS'], $before);
check('Nach 3 h Ausfall (19:00:10, 19:00:40, 21:00 verpasst): genau EINE Schaltung, der letzte Stand „an"', count($missed) === 1 && $missed[0][2] === true, json_encode($missed));
// Rückkehr: Simulation aus, es wird wieder gelernt — die Tage vor der Abreise sind noch da.
clock('2026-08-14 10:00:00'); $m2->RequestAction('Active', false);
clock('2026-08-14 18:00:00'); fire($m2, 300, true); clock('2026-08-14 22:00:00'); fire($m2, 300, false);
$after = $bufOf(700, 300);
check('Nach der Rückkehr: Gelerntes von vor der Abreise bleibt (Simulationszeit zählt nicht zum Fenster)', count($after) > 100 && date('Y-m-d', $after[0]['t']) <= '2026-06-03', count($after) . ' Ereignisse, ältestes ' . date('d.m.Y', $after[0]['t']));
check('Simulationszeit ist abgeschlossen gemerkt', (json_decode(GetValueString(IPS_GetObjectIDByIdent('SimPeriods', 700)), true)[0] ?? null) === [ts('2026-06-29 08:00:00'), ts('2026-08-14 10:00:00')]);
// Resync mitten in der Simulation: die Simulationszeiten sind eine Variable und überleben.
clock('2026-08-15 08:00:00'); $m2->RequestAction('Active', true); clock('2026-09-20 08:00:00'); $m2->simulateResync();
check('Resync nach 5 Wochen Simulation: Vorschau hat weiter ein Vorbild (kein Leerlaufen)', preg_match('/Stehlampe \(Vorbild/', $m2->Preview()) === 1, $m2->Preview());
check('Resync: „seit …" wird aus der gemerkten Simulationszeit wiederhergestellt (nicht 01.01.1970)', $m2->attrs['ActiveSince'] === ts('2026-08-15 08:00:00') && str_contains($m2->Preview(), 'seit 08:00 Uhr'), (string)$m2->attrs['ActiveSince']);
$m2->RequestAction('Active', false);

heading('18. Mindestzahl Schaltvorgänge je Vorbild-Tag (0.3.0)');
mkvar(310, VARIABLETYPE_BOOLEAN, false, 'Flurlicht', '', 1, true);
clock('2026-03-01 12:00:00');
$m3 = $mk(701, [['Caption' => 'Flurlicht', 'TargetID' => 310, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
// Mo 02.03. lebhaft (4 Schaltungen), Mo 09.03. fast leer (1 Schaltung — niemand zu Hause), Mo 16.03. = heute.
foreach ([['2026-03-02 18:00:00', true], ['2026-03-02 19:00:00', false], ['2026-03-02 21:00:00', true], ['2026-03-02 23:00:00', false], ['2026-03-09 03:00:00', true]] as [$t, $v]) { clock($t); fire($m3, 310, $v); }
clock('2026-03-16 12:00:00'); $m3->index = 1; // „Zufall" wählt den zweiten Kandidaten, wenn es ihn gibt
check('Standard (4): der fast leere Montag scheidet aus, Vorbild ist der lebhafte 02.03.', str_contains($m3->Preview(), 'Vorbild Mo 02.03.2026'), $m3->Preview());
$m3->props['MinDayEvents'] = 0; $m3->ApplyChanges();
check('Mindestzahl 0: der fast leere 09.03. kann gewählt werden', str_contains($m3->Preview(), 'Vorbild Mo 09.03.2026'), $m3->Preview());
$m3->props['MinDayEvents'] = 50; $m3->ApplyChanges();
$p50 = $m3->Preview();
check('Mindestzahl unerreichbar (50): Hinweis, es gelten die vorhandenen Tage — kein dunkles Haus', str_contains($p50, 'Kein gelernter Tag erreicht die Mindestzahl von 50') && str_contains($p50, 'Vorbild Mo'), $p50);
check('… derselbe Hinweis beim Start', str_contains($m3->SetActive(true), 'Mindestzahl von 50'));
$m3->RequestAction('Active', false);

heading('19. Archiv-Import: Lernphase verkürzen (0.3.0)');
$GLOBALS['ARCHIVE_INST'] = [53244];
mkvar(320, VARIABLETYPE_BOOLEAN, false, 'Esstisch', '', 1, true);
mkvar(321, VARIABLETYPE_BOOLEAN, false, 'Ohne Logging', '', 1, true);
mkvar(322, VARIABLETYPE_INTEGER, 0, 'Dimmer Bad', '', 1, true);
// Archiv: 20 Tage Verlauf vor „heute" (20.05.), davor ein Alt-Wert außerhalb des Lernfensters; mit doppelten
// Werten (gleicher Wert erneut) und einem Dimmer-Schieber binnen Sekunden.
$arch = [[ts('2026-03-01 18:00:00'), true]];
for ($i = 20; $i >= 1; $i--) { $d = date('Y-m-d', ts('2026-05-20 -' . $i . ' days')); $arch[] = [ts("$d 18:30:00"), true]; $arch[] = [ts("$d 18:45:00"), true]; $arch[] = [ts("$d 22:15:00"), false]; }
$GLOBALS['ARCHIVE'][320] = $arch;
$GLOBALS['ARCHIVE'][322] = [[ts('2026-05-10 20:00:00'), 30], [ts('2026-05-10 20:00:05'), 70], [ts('2026-05-10 20:00:09'), 100], [ts('2026-05-10 23:00:00'), 0]];
clock('2026-05-20 12:00:00');
$m4 = $mk(702, [['Caption' => 'Esstisch', 'TargetID' => 320, 'Mode' => 1, 'TimeRef' => 1, 'JitterMin' => 0], ['Caption' => 'Ohne Logging', 'TargetID' => 321, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Dimmer Bad', 'TargetID' => 322, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$b320 = $bufOf(702, 320);
check('Neuer Kanal: Verlauf automatisch aus dem Archiv übernommen (20 Tage × an/aus = 40, doppelte Werte verworfen)', count($b320) === 40, (string)count($b320));
check('Nur das Lernfenster: der Alt-Wert vom 01.03. bleibt draußen', date('Y-m-d', $b320[0]['t']) === '2026-04-30', date('d.m.Y', $b320[0]['t']));
$ssInfo = date_sun_info($b320[0]['t'], 48.47, 7.94);
check('Sonnen-Anker der übernommenen Ereignisse aus dem Standort berechnet (Sonnenuntergang des Tages)', ($b320[0]['ss'] ?? 0) === $ssInfo['sunset'] && isset($b320[0]['sr']), json_encode($b320[0]) . ' / ' . $ssInfo['sunset']);
check('Kanal ohne Logging: bleibt leer, lernt selbst', count($bufOf(702, 321)) === 0);
$b322 = $bufOf(702, 322);
check('Dimmer-Schieber im Archiv (3 Werte in 9 s) = ein Schaltvorgang mit Endwert 100, Typ Integer', count($b322) === 2 && $b322[0]['v'] === 100 && $b322[1]['v'] === 0, json_encode($b322));
check('Sofort nutzbar: Vorschau am ersten Tag nennt ein Vorbild für Esstisch', preg_match('/Esstisch \(Vorbild/', $m4->Preview()) === 1, $m4->Preview());
// Eigenes Lernen danach; erneuter Import ergänzt nichts und verdoppelt nichts.
clock('2026-05-20 19:00:00'); fire($m4, 320, true); clock('2026-05-20 23:00:00'); fire($m4, 320, false);
$GLOBALS['ARCHIVE'][320][] = [ts('2026-05-20 19:00:00'), true]; $GLOBALS['ARCHIVE'][320][] = [ts('2026-05-20 23:00:00'), false];
clock('2026-05-21 09:00:00'); $n1 = count($bufOf(702, 320)); $rImp = $m4->ImportArchive();
check('Knopf bei schon gefülltem Lernfenster: nichts doppelt', count($bufOf(702, 320)) === $n1 && str_contains($rImp, 'Esstisch: nichts zu ergänzen'), $rImp);
check('Rückmeldung nennt den Kanal ohne Logging', str_contains($rImp, 'Ohne Logging: wird nicht im Archiv geloggt') && str_starts_with($rImp, '📥 Archiv-Import:'), $rImp);
check('Knopf frischt Statuszeile und Kanal-Zusammenfassung gemeinsam auf', count(array_filter($m4->fieldUpdates, fn($u) => in_array($u[0], ['SimStatus', 'ChannelSummary'], true))) === 2);
// Bestehender Kanal, der erst seit Kurzem selbst lernt: der Knopf füllt die Zeit DAVOR.
mkvar(323, VARIABLETYPE_BOOLEAN, false, 'Sofa', '', 1, true);
clock('2026-05-21 09:30:00');
$m5 = $mk(703, [['Caption' => 'Sofa', 'TargetID' => 323, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
clock('2026-05-21 20:00:00'); fire($m5, 323, true); clock('2026-05-21 22:00:00'); fire($m5, 323, false);
$own = $bufOf(703, 323);
$GLOBALS['ARCHIVE'][323] = [[ts('2026-05-10 20:00:00'), true], [ts('2026-05-10 22:00:00'), false], [ts('2026-05-21 20:00:00'), true], [ts('2026-05-21 22:00:00'), false]];
clock('2026-05-22 09:00:00'); $r5 = $m5->ImportArchive(); $b5 = $bufOf(703, 323);
check('Bestehender Kanal: 2 ältere Schaltvorgänge ergänzt, die eigenen 2 unverändert, zeitlich sortiert', count($b5) === 4 && array_slice($b5, 2) === $own && $b5[0]['t'] === ts('2026-05-10 20:00:00') && str_contains($r5, '2 Schaltvorgänge aus dem Archiv übernommen') && str_contains($r5, '10.05.2026'), $r5 . ' / ' . json_encode(array_column($b5, 't')));
// Zeiten, in denen die Simulation lief, werden nicht aus dem Archiv gelernt (dort steht die Wiedergabe).
mkvar(324, VARIABLETYPE_BOOLEAN, false, 'Küchenzeile', '', 1, true); mkvar(325, VARIABLETYPE_BOOLEAN, false, 'Hilfskanal', '', 1, true);
clock('2026-05-01 12:00:00');
$m6 = $mk(704, [['Caption' => 'Hilfskanal', 'TargetID' => 325, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
clock('2026-05-05 08:00:00'); $m6->RequestAction('Active', true); clock('2026-05-08 08:00:00'); $m6->RequestAction('Active', false); // 3 Tage Simulation
$GLOBALS['ARCHIVE'][324] = [[ts('2026-05-03 19:00:00'), true], [ts('2026-05-03 22:00:00'), false], [ts('2026-05-06 19:00:00'), true], [ts('2026-05-06 22:00:00'), false], [ts('2026-05-10 19:00:00'), true], [ts('2026-05-10 22:00:00'), false]];
clock('2026-05-12 12:00:00');
$m6->props['Channels'] = json_encode([['Caption' => 'Hilfskanal', 'TargetID' => 325, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Küchenzeile', 'TargetID' => 324, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]); $m6->ApplyChanges();
$b6 = array_map(fn($e) => date('d.m.', $e['t']), $bufOf(704, 324));
check('Archiv-Werte aus der Simulationszeit (06.05.) werden übersprungen', $b6 === ['03.05.', '03.05.', '10.05.', '10.05.'], implode(' ', $b6));
$GLOBALS['ARCHIVE_INST'] = [];
check('Ohne Archive Control: Knopf meldet „nicht geloggt", nichts bricht', str_contains($m4->ImportArchive(), 'wird nicht im Archiv geloggt'));

heading('20. Geräte, die nie geschaltet werden (Toaster, Bügeleisen …) (0.3.0)');
$GLOBALS['INSTANCES'][9001] = 'Toaster Küche';
mkvar(400, VARIABLETYPE_BOOLEAN, false, 'Bügeleisen', '', 1, true);
mkvar(401, VARIABLETYPE_BOOLEAN, false, 'Stehlampe', '', 1, true);
mkvar(402, VARIABLETYPE_BOOLEAN, false, 'Zustand', '', 9001, true);
mkvar(403, VARIABLETYPE_BOOLEAN, false, 'Theater Licht', '', 1, true);
mkvar(405, VARIABLETYPE_BOOLEAN, false, 'Environment light', '', 1, true);
clock('2026-06-01 12:00:00');
$m7 = $mk(710, [['Caption' => 'Steckdose 1', 'TargetID' => 400, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Stehlampe', 'TargetID' => 401, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0],
    ['Caption' => 'Frühstück', 'TargetID' => 402, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Theater', 'TargetID' => 403, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0],
    ['Caption' => 'Environment', 'TargetID' => 405, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$m7->props['MinDayEvents'] = 0; $m7->ApplyChanges();
foreach (['2026-06-01', '2026-06-08'] as $d) { foreach ([400, 401, 402, 403, 405] as $v) { clock("$d 19:00:00"); fire($m7, $v, true); clock("$d 22:00:00"); fire($m7, $v, false); } }
check('Gesperrte Kanäle lernen trotzdem mit (Sperre betrifft nur das Schalten)', count($bufOf(710, 400)) === 4 && count($bufOf(710, 402)) === 4);
clock('2026-06-15 12:00:00'); $n0 = count($GLOBALS['ACTIONS']);
$p7 = $m7->Preview();
check('Vorschau: Bügeleisen (Variablenname) und Toaster (Gerätename) werden nie geschaltet, mit Begründung', str_contains($p7, 'Steckdose 1: wird nie geschaltet („Bügeleisen"') && str_contains($p7, 'Frühstück: wird nie geschaltet („Toaster"'), $p7);
check('Vorschau: Stehlampe, Theater und Environment haben ein Vorbild („Heater"/„Iron" sperren kein Theater/Environment)', str_contains($p7, 'Stehlampe (Vorbild') && str_contains($p7, 'Theater (Vorbild') && str_contains($p7, 'Environment (Vorbild'), $p7);
$m7->RequestAction('Active', true);
clock('2026-06-15 19:00:05'); $m7->Tick(); clock('2026-06-15 22:00:05'); $m7->Tick();
$ids7 = array_values(array_unique(array_map(fn($a) => $a[1], actionsFrom($n0)))); sort($ids7);
check('Simulation schaltet nur die drei unverdächtigen Kanäle — Bügeleisen und Toaster nie', $ids7 === [401, 403, 405], json_encode($ids7));
check('Kanal-Zusammenfassung im Formular zeigt ⛔ mit dem Sperrwort', str_contains(formText(form($m7)), '⛔ Steckdose 1: wird nie geschaltet') && str_contains(formText(form($m7)), 'Geräte nie schalten, deren Name eines dieser Wörter enthält'), '');
$m7->RequestAction('Active', false);
$GLOBALS['INSTANCES'][9002] = 'Haustüre'; $GLOBALS['INSTANCES'][9003] = 'Eingang';
mkvar(406, VARIABLETYPE_INTEGER, 0, 'Türschloß (Modus)', '', 9002, true); mkvar(407, VARIABLETYPE_INTEGER, 0, 'Modus', '', 9003, true); $GLOBALS['PROFILES'][407] = ['~DoorLockMode.ZWave', ''];
mkvar(408, VARIABLETYPE_BOOLEAN, false, 'Licht Garage', '', 1, true);
$m7->props['Channels'] = json_encode(array_merge(json_decode($m7->props['Channels'], true), [['Caption' => 'Schloss vorn', 'TargetID' => 406, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Eingang', 'TargetID' => 407, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Garage', 'TargetID' => 408, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]])); $m7->ApplyChanges();
$p7b = $m7->Preview();
check('Türschloss wird nie geschaltet — erkannt am Namen („Türschloß") und allein am Profil (DoorLock)', str_contains($p7b, 'Schloss vorn: wird nie geschaltet („Türschloß"') && str_contains($p7b, 'Eingang: wird nie geschaltet („DoorLock"'), $p7b);
check('… das Licht in der Garage bleibt ein gewöhnlicher Kanal (nur „Garagentor" steht auf der Liste)', !str_contains($p7b, 'Garage: wird nie geschaltet'), $p7b);
$m7->props['BlockedWords'] = 'Sauna'; $m7->ApplyChanges();
check('Liste gehört dem Nutzer: ohne das Wort wird der Kanal wieder abgespielt', str_contains($m7->Preview(), 'Steckdose 1 (Vorbild'), $m7->Preview());
$m7->props['BlockedWords'] = ' , lampe,, '; $m7->ApplyChanges();
check('Kurzes Wort (< 7 Buchstaben) greift nur am Wortanfang: „lampe" sperrt „Stehlampe" nicht, leere Einträge sind egal', str_contains($m7->Preview(), 'Stehlampe (Vorbild'), $m7->Preview());

heading('21. Feiertage wie Sonntage (0.3.0)');
$hol = new ReflectionMethod(DG65Anwesenheitssimulation::class, 'holidaysOfYear');
$bw = $hol->invoke(null, 2026, 'BW'); $sn = $hol->invoke(null, 2026, 'SN'); $at = $hol->invoke(null, 2026, 'AT'); $chH = $hol->invoke(null, 2026, 'CH');
check('BW 2026: Dreikönig, Karfreitag 03.04., Ostermontag 06.04., Himmelfahrt 14.05., Pfingstmontag 25.05., Fronleichnam 04.06., Allerheiligen', isset($bw['2026-01-06'], $bw['2026-04-03'], $bw['2026-04-06'], $bw['2026-05-14'], $bw['2026-05-25'], $bw['2026-06-04'], $bw['2026-11-01'], $bw['2026-10-03'], $bw['2026-12-26']), implode(' ', array_keys($bw)));
check('BW: kein Reformationstag, kein Buß- und Bettag; 12 Feiertage', !isset($bw['2026-10-31']) && !isset($bw['2026-11-18']) && count($bw) === 12, (string)count($bw));
check('Sachsen: Reformationstag und Buß- und Bettag (Mi 18.11.2026), kein Fronleichnam', isset($sn['2026-10-31'], $sn['2026-11-18']) && !isset($sn['2026-06-04']));
check('Österreich: Nationalfeiertag 26.10., Mariä Empfängnis 08.12.; Schweiz: Bundesfeier 01.08.', isset($at['2026-10-26'], $at['2026-12-08'], $at['2026-01-06']) && isset($chH['2026-08-01']) && !isset($chH['2026-05-01']));
check('Ostern 2027 (28.03.): Karfreitag 26.03.2027', isset($hol->invoke(null, 2027, 'NW')['2027-03-26']));
mkvar(410, VARIABLETYPE_BOOLEAN, false, 'Esszimmer', '', 1, true);
clock('2026-05-10 12:00:00');
$m8 = $mk(711, [['Caption' => 'Esszimmer', 'TargetID' => 410, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$m8->props['MinDayEvents'] = 0; $m8->ApplyChanges();
// Do 14.05. Himmelfahrt (spätes Frühstück), So 17.05. (Sonntag), Mo 18.05. (Werktag)
foreach ([['2026-05-14 11:00:00', true], ['2026-05-14 13:00:00', false], ['2026-05-17 10:00:00', true], ['2026-05-17 12:00:00', false], ['2026-05-18 19:00:00', true], ['2026-05-18 22:00:00', false]] as [$tt, $v]) { clock($tt); fire($m8, 410, $v); }
clock('2026-05-25 08:00:00'); $m8->index = 0; // Pfingstmontag
check('Ohne Region: Pfingstmontag ist ein gewöhnlicher Montag → Vorbild Mo 18.05.', str_contains($m8->Preview(), 'Vorbild Mo 18.05.2026') && !str_contains($m8->Preview(), 'Feiertag'), $m8->Preview());
$m8->props['HolidayRegion'] = 'BW'; $m8->ApplyChanges();
$p8 = $m8->Preview() . ' | '; $m8->index = 1; $p8 .= $m8->Preview(); $m8->index = 0;
check('Mit Region: Pfingstmontag wird wie ein Sonntag abgespielt, mit Hinweis', str_contains($p8, 'Heute ist ein Feiertag') && !str_contains($p8, 'Vorbild Mo 18.05.2026'), $p8);
check('… und ein gelernter Feiertag (Do 14.05. Himmelfahrt) zählt selbst als Sonntag: Kandidaten sind 14.05. und 17.05.', str_contains($p8, 'Do 14.05.2026') && str_contains($p8, 'So 17.05.2026'), $p8);
clock('2026-05-28 08:00:00'); // gewöhnlicher Donnerstag
check('Gewöhnlicher Donnerstag nimmt den Feiertags-Donnerstag nicht als „gleichen Wochentag", sondern einen Werktag', str_contains($m8->Preview(), 'Vorbild Mo 18.05.2026'), $m8->Preview());

heading('22. Auslöser: Zahl-/Text-Variable, Werteliste, Startverzögerung (0.3.0)');
mkvar(420, VARIABLETYPE_INTEGER, 0, 'Hausmodus', '', 1, true); mkvar(421, VARIABLETYPE_STRING, '', 'Modus', '', 1, false); mkvar(422, VARIABLETYPE_BOOLEAN, false, 'Deckenlicht', '', 1, true);
$GLOBALS['FORMATTED'][420] = ['0' => 'Tag', '3' => 'Abwesend', '4' => 'Urlaub'];
clock('2026-07-06 09:00:00');
$m9 = $mk(712, [['Caption' => 'Deckenlicht', 'TargetID' => 422, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$m9->props['TriggerID'] = 420; $m9->props['TriggerValues'] = '3, 4'; $m9->ApplyChanges();
fire($m9, 420, 3);
check('Hausmodus 3 (Abwesend) steht in der Werteliste → Simulation an', $m9->GetValue('Active') === true);
$since = $m9->attrs['ActiveSince']; clock('2026-07-06 09:30:00'); fire($m9, 420, 4);
check('Wechsel 3 → 4 (Urlaub): läuft weiter, kein Neustart', $m9->GetValue('Active') === true && $m9->attrs['ActiveSince'] === $since);
check('Formular zeigt, was der Auslöser gerade sagt (formatierter Wert, Rohwert, Wirkung)', str_contains(formText(form($m9)), 'Auslöser-Variable jetzt: Urlaub (Rohwert 4) → simulieren.'), '');
fire($m9, 420, 0);
check('Hausmodus 0 (Tag) → Simulation aus', $m9->GetValue('Active') === false && str_contains(formText(form($m9)), '(Rohwert 0) → nicht simulieren.'));
$m9->props['TriggerInvert'] = true; $m9->ApplyChanges(); fire($m9, 420, 1);
check('Umgekehrt: Wert NICHT in der Liste → Simulation an', $m9->GetValue('Active') === true);
fire($m9, 420, 3); $m9->props['TriggerInvert'] = false;
$m9->props['TriggerID'] = 421; $m9->props['TriggerValues'] = 'Abwesend, Urlaub'; $m9->ApplyChanges();
fire($m9, 421, 'urlaub');
check('Text-Variable: Vergleich ohne Rücksicht auf Groß-/Kleinschreibung', $m9->GetValue('Active') === true);
fire($m9, 421, 'Zuhause');
check('Text-Variable: anderer Wert → aus', $m9->GetValue('Active') === false);
$m9->props['TriggerID'] = 420; $m9->props['TriggerValues'] = '3,4'; $m9->props['TriggerDelay'] = 30; $m9->ApplyChanges();
clock('2026-07-06 10:00:00'); fire($m9, 420, 3);
check('Startverzögerung 30 min: noch nicht aktiv, Zeitgeber läuft (1 800 000 ms)', $m9->GetValue('Active') === false && $m9->timers['StartDelay'] === 1800000, (string)$m9->timers['StartDelay']);
$until = $m9->attrs['DelayUntil']; clock('2026-07-06 10:10:00'); $m9->timers['StartDelay'] = -1; fire($m9, 420, 3);
check('Erneutes Schreiben desselben Werts startet die Verzögerung nicht von vorn', $m9->attrs['DelayUntil'] === $until && $m9->timers['StartDelay'] === -1);
clock('2026-07-06 10:15:00'); fire($m9, 420, 0); $m9->TriggerDelayElapsed();
check('Kurzer Einkauf: nach 15 min zurück → Verzögerung verfällt, nie gestartet', $m9->GetValue('Active') === false && $m9->timers['StartDelay'] === 0 && $m9->attrs['DelayUntil'] === 0);
clock('2026-07-06 11:00:00'); fire($m9, 420, 3); clock('2026-07-06 11:30:00'); $m9->TriggerDelayElapsed();
check('Länger weg: nach Ablauf der Verzögerung startet die Simulation', $m9->GetValue('Active') === true && $m9->timers['StartDelay'] === 0);
fire($m9, 420, 0);
check('Beenden wirkt sofort, ohne Verzögerung', $m9->GetValue('Active') === false);
clock('2026-07-06 12:00:00'); fire($m9, 420, 3); $GLOBALS['VARS'][420]['value'] = 0; clock('2026-07-06 12:30:00'); $m9->TriggerDelayElapsed();
check('Ablauf der Verzögerung prüft den aktuellen Wert: Auslöser sagt nicht mehr „simulieren" → kein Start', $m9->GetValue('Active') === false);
$m9->props['TriggerDelay'] = 0; $m9->props['TriggerID'] = 0; $m9->ApplyChanges();
check('Ohne Auslöser: Hinweis statt Statuszeile', str_contains(formText(form($m9)), 'Keine Auslöser-Variable gewählt'));

heading('23. Ende der Simulation: lassen, wiederherstellen oder ausschalten (0.3.0)');
mkvar(430, VARIABLETYPE_BOOLEAN, false, 'Lampe A', '', 1, true); mkvar(431, VARIABLETYPE_BOOLEAN, false, 'Lampe B', '', 1, true); mkvar(432, VARIABLETYPE_INTEGER, 0, 'Dimmer C', '', 1, true);
clock('2026-07-01 12:00:00');
$chs10 = [['Caption' => 'Lampe A', 'TargetID' => 430, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Lampe B', 'TargetID' => 431, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Dimmer C', 'TargetID' => 432, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]];
$m10 = $mk(713, $chs10); $m10->props['MinDayEvents'] = 0; $m10->ApplyChanges();
foreach (['2026-07-06', '2026-07-13'] as $d) { clock("$d 19:00:00"); fire($m10, 430, true); fire($m10, 431, true); fire($m10, 432, 80); clock("$d 22:00:00"); fire($m10, 430, false); fire($m10, 431, false); fire($m10, 432, 0); }
// Abreise Mo 20.07.: Lampe A brennt noch (vergessen), Dimmer steht auf 40.
clock('2026-07-20 08:00:00'); fire($m10, 430, true); fire($m10, 432, 40);
$run = function (int $endAction) use ($m10): array { $m10->props['EndAction'] = $endAction; $m10->ApplyChanges(); clock('2026-07-20 12:00:00'); $m10->SetActive(true); clock('2026-07-20 19:00:05'); $m10->Tick(); clock('2026-07-20 20:00:00'); $n = count($GLOBALS['ACTIONS']); $r = $m10->SetActive(false); return [$r, actionsFrom($n)]; };
$val = fn(int $id) => $GLOBALS['VARS'][$id]['value'];
[$r0, $a0] = $run(0);
check('Vorgabe „alles lassen": beim Beenden wird nichts geschaltet', $a0 === [] && $val(430) === true && $val(431) === true && $val(432) === 80 && str_contains($r0, 'Die Zielvariablen bleiben so, wie sie gerade stehen'), json_encode($a0));
// Ausgangslage für die nächsten Läufe wiederherstellen (echte Handgriffe, werden gelernt)
$reset = function () use ($m10) { clock('2026-07-20 08:00:00'); fire($m10, 431, false); fire($m10, 432, 40); fire($m10, 430, true); };
$reset(); [$r1, $a1] = $run(1);
check('„Wiederherstellen": Lampe A wieder an, Lampe B wieder aus, Dimmer wieder 40', $val(430) === true && $val(431) === false && $val(432) === 40 && str_contains($r1, 'Zustand von vor dem Start ist wiederhergestellt'), json_encode([$val(430), $val(431), $val(432)]) . ' ' . $r1);
check('… geschaltet wird nur, was abweicht (Lampe B und Dimmer; Lampe A stand schon richtig)', array_map(fn($a) => $a[1], $a1) === [431, 432], json_encode($a1));
check('„Letzte Schaltung" nennt den Grund', str_contains($m10->GetValue('LastAction'), 'Zustand von vor der Simulation wiederhergestellt'), $m10->GetValue('LastAction'));
$n431 = count($bufOf(713, 431)); clock('2026-07-20 20:00:03'); fire($m10, 431, false);
check('Späte Rückmeldung der Endaktion wird nicht als echtes Verhalten gelernt', count($bufOf(713, 431)) === $n431);
$reset(); [$r2, $a2] = $run(2);
check('„Ausschalten": alle abgespielten Kanäle aus bzw. 0', $val(430) === false && $val(431) === false && $val(432) === 0 && str_contains($r2, 'die abgespielten Kanäle sind ausgeschaltet') && str_contains($m10->GetValue('LastAction'), 'am Ende der Simulation ausgeschaltet'), $r2);
$n430 = count($bufOf(713, 430)); clock('2026-07-20 20:00:03'); fire($m10, 430, false);
check('Ausschalten am Ende wird nicht gelernt', count($bufOf(713, 430)) === $n430, count($bufOf(713, 430)) . ' statt ' . $n430);
clock('2026-07-20 21:00:00'); fire($m10, 430, true); $b430 = $bufOf(713, 430);
check('Erste echte Schaltung nach der Rückkehr wird gelernt, obwohl der Puffer noch „An" von vor der Abreise kennt', count($b430) === $n430 + 1 && end($b430)['t'] === ts('2026-07-20 21:00:00') && end($b430)['v'] === true, json_encode(array_slice($b430, -2)));
clock('2026-07-20 21:00:30'); fire($m10, 430, true);
check('… danach gilt wieder die gewohnte Regel: Schreiben ohne Wertänderung wird nicht gelernt', count($bufOf(713, 430)) === $n430 + 1);
$reset(); $m10->props['EndAction'] = 1; $m10->ApplyChanges(); clock('2026-07-20 12:00:00'); $m10->SetActive(true); $m10->simulateResync(); $m10->props['Channels'] = json_encode($chs10); $m10->props['EndAction'] = 1; $m10->props['MinDayEvents'] = 0;
clock('2026-07-20 20:00:00'); $n = count($GLOBALS['ACTIONS']); $m10->SetActive(false);
check('Startzustand unbekannt (Modul-Resync während der Simulation): lieber nichts schalten als raten', actionsFrom($n) === [], json_encode(actionsFrom($n)));
mkvar(433, VARIABLETYPE_BOOLEAN, true, 'Lampe D', '', 1, true);
$m10->props['Channels'] = json_encode(array_merge($chs10, [['Caption' => 'Lampe D', 'TargetID' => 433, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]])); $m10->props['EndAction'] = 2; $m10->ApplyChanges();
clock('2026-07-20 12:00:00'); $m10->SetActive(true); clock('2026-07-20 20:00:00'); $m10->SetActive(false);
check('„Ausschalten" fasst nur an, was die Simulation abgespielt hat: Kanal ohne Gelerntes bleibt, wie er ist', $val(433) === true);

heading('24. Übersicht „Heutiger Ablauf" für die Visualisierung (0.3.0)');
mkvar(440, VARIABLETYPE_BOOLEAN, false, 'Leselampe', '', 1, true);
clock('2026-08-03 12:00:00');
$m11 = $mk(714, [['Caption' => '<b>Lese</b> & Co', 'TargetID' => 440, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]); $m11->props['MinDayEvents'] = 0; $m11->ApplyChanges();
$sched = fn() => GetValueString(IPS_GetObjectIDByIdent('Schedule', 714));
check('Variable „Heutiger Ablauf" angelegt; ohne Simulation steht dort, dass gelernt wird', IPS_GetObjectIDByIdent('Schedule', 714) !== false && IPS_GetName(IPS_GetObjectIDByIdent('Schedule', 714)) === 'Heutiger Ablauf' && str_contains($sched(), 'Simulation ist aus'), $sched());
clock('2026-08-03 19:00:00'); fire($m11, 440, true); clock('2026-08-03 22:00:00'); fire($m11, 440, false);
clock('2026-08-10 12:00:00'); $m11->SetActive(true); $h1 = $sched();
check('Simulation läuft: Tabelle mit Vorbild-Tag und nächstem Stand (An um 19:00)', str_contains($h1, '<table') && str_contains($h1, '03.08.2026') && preg_match('~<td[^>]*>An</td><td[^>]*>19:00</td></tr>~', $h1) === 1, $h1);
check('Kanalname wird HTML-sicher ausgegeben (kein fremdes Markup in der Visualisierung)', str_contains($h1, '&lt;b&gt;Lese&lt;/b&gt; &amp; Co') && !str_contains($h1, '<b>Lese'), $h1);
clock('2026-08-10 19:00:05'); $m11->Tick(); $h2 = $sched();
check('Nach der Schaltung: aktueller Stand „An" seit 19:00, nächster „Aus" um 22:00', preg_match('~<td[^>]*>An</td><td[^>]*>19:00</td><td[^>]*>Aus</td><td[^>]*>22:00</td></tr>~', $h2) === 1, $h2);
$m11->SetActive(false);
// Vorbild-Tag, an dem ein zweiter Kanal gar nicht geschaltet wurde: Hinweis statt nur Striche (0.3.2).
mkvar(441, VARIABLETYPE_BOOLEAN, false, 'Kellerlampe', '', 1, true);
clock('2026-08-11 12:00:00');
$m11->props['Channels'] = json_encode([['Caption' => 'Lese', 'TargetID' => 440, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Keller', 'TargetID' => 441, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]); $m11->ApplyChanges();
clock('2026-08-02 09:00:00'); fire($m11, 441, true); clock('2026-08-02 09:30:00'); fire($m11, 441, false); // Keller nur am So 02.08. geschaltet
clock('2026-08-17 12:00:00'); $m11->SetActive(true); // Montag: gemeinsames Vorbild Mo 03.08. — an dem Tag hat der Keller nicht geschaltet
check('Kanal ohne Schaltung am Vorbild-Tag: „heute keine Schaltung" statt nur Striche', (bool)preg_match('~Keller</td><td[^>]*>Mo 03\.08\.2026</td><td[^>]*>–</td><td[^>]*>–</td><td[^>]*>heute keine Schaltung</td>~', $sched()), $sched());
$m11->SetActive(false);
check('Nach dem Beenden wieder der Lern-Hinweis', str_contains($sched(), 'Simulation ist aus') && !str_contains($sched(), '<table'));

heading('25. Vorschläge: passende Variablen aus dem Archiv (0.3.0)');
$GLOBALS['ARCHIVE'] = []; $GLOBALS['ARCHIVE_INST'] = [53244];
$GLOBALS['INSTANCES'][9100] = 'Stehlampe Wohnzimmer';
mkvar(500, VARIABLETYPE_BOOLEAN, false, 'Zustand', '', 9100, true);          // Leuchte, Name kommt vom Gerät
mkvar(501, VARIABLETYPE_BOOLEAN, false, 'Ladefreigabe WB 1', '', 1, true);   // schaltbar, aber keine Beleuchtung
mkvar(502, VARIABLETYPE_BOOLEAN, false, 'Bügeleisen', '', 1, true);          // Sperrliste
mkvar(503, VARIABLETYPE_BOOLEAN, false, 'Fensterkontakt Licht', '', 1, false); // keine Aktion
mkvar(504, VARIABLETYPE_INTEGER, 0, 'Dimmer Licht', '', 1, true);            // Dimmer (Zahl-Variable)
mkvar(512, VARIABLETYPE_FLOAT, 0.0, 'Sollwert Heizen', '', 1, true); $GLOBALS['PROFILES'][512] = ['~Temperature.Room', '']; // schaltbare Zahl, aber weder Dimmer noch Rollladen
mkvar(505, VARIABLETYPE_BOOLEAN, false, 'Kellerlicht', '', 1, true);         // nur an 2 Tagen geschaltet
mkvar(506, VARIABLETYPE_BOOLEAN, false, 'Taktgeber Licht', '', 1, true);     // Dauerfeuer
mkvar(507, VARIABLETYPE_BOOLEAN, false, 'Flurlicht oben', '', 1, true);      // ist schon Kanal
clock('2026-09-20 12:00:00');
$m12 = $mk(715, [['Caption' => 'Flur oben', 'TargetID' => 507, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$days = fn(int $n, array $times) => (function () use ($n, $times) { $o = []; for ($i = $n; $i >= 1; $i--) { $d = date('Y-m-d', ts('2026-09-20 -' . $i . ' days')); foreach ($times as [$hm, $v]) { $o[] = [ts("$d $hm"), $v]; } } return $o; })();
$GLOBALS['ARCHIVE'][500] = $days(10, [['19:00:00', true], ['19:30:00', true], ['22:00:00', false]]); // doppelter Wert zählt nicht
$GLOBALS['ARCHIVE'][501] = $days(6, [['01:00:00', true], ['05:00:00', false]]);
foreach ([502, 503, 507] as $v) { $GLOBALS['ARCHIVE'][$v] = $days(10, [['19:00:00', true], ['22:00:00', false]]); }
$GLOBALS['ARCHIVE'][504] = $days(10, [['19:00:00', 80], ['22:00:00', 0]]);
$GLOBALS['ARCHIVE'][512] = $days(10, [['06:00:00', 21.5], ['22:00:00', 18.0]]);
$GLOBALS['ARCHIVE'][505] = $days(2, [['19:00:00', true], ['22:00:00', false]]);
$takt = []; for ($i = 5; $i >= 1; $i--) { $d0 = ts('2026-09-20 -' . $i . ' days'); for ($k = 0; $k < 400; $k++) { $takt[] = [$d0 + $k * 120, $k % 2 === 0]; } } $GLOBALS['ARCHIVE'][506] = $takt;
$GLOBALS['ARCHIVE'][IPS_GetObjectIDByIdent('Active', 715)] = $days(10, [['19:00:00', true], ['22:00:00', false]]); // eigene Variable
$m12->fieldUpdates = []; $rD = $m12->Discover();
$upd = fn(string $name, string $prop) => (function () use ($m12, $name, $prop) { $hit = null; foreach ($m12->fieldUpdates as $u) { if ($u[0] === $name && $u[1] === $prop) { $hit = $u[2]; } } return $hit; })();
$sug = json_decode((string)$upd('Suggestions', 'values'), true);
check('Suche findet Licht, Dimmer und sonstigen Schalter (nicht: Sperrliste, ohne Aktion, Heizungs-Sollwert, zu selten, Dauerfeuer, schon Kanal, eigene Variable)', is_array($sug) && array_column($sug, 'TargetID') === [500, 504, 501], json_encode($sug, JSON_UNESCAPED_UNICODE));
check('Spalte „Art": Licht, Dimmer, Sonstiges', array_column($sug, 'Kind') === ['Licht', 'Dimmer', 'Sonstiges'], json_encode(array_column($sug, 'Kind'), JSON_UNESCAPED_UNICODE));
check('Name vom Gerät, 10 Tage, 2 Schaltungen je Tag (doppelte Archivwerte zählen nicht)', ($sug[0]['Caption'] ?? '') === 'Stehlampe Wohnzimmer' && ($sug[0]['Days'] ?? 0) === 10 && (float)($sug[0]['PerDay'] ?? 0) === 2.0, json_encode($sug[0] ?? null));
check('Vor-angehakt sind Licht und Dimmer — die Ladefreigabe der Wallbox nicht', ($sug[0]['Pick'] ?? null) === true && ($sug[1]['Pick'] ?? null) === true && ($sug[2]['Pick'] ?? null) === false);
$sug[1]['Pick'] = false; // der Nutzer nimmt den Haken beim Dimmer heraus
check('Rückmeldung und Statuszeile; Liste und Übernehmen-Knopf werden sichtbar', str_contains($rD, '3 passende Variablen gefunden') && str_contains((string)$upd('DiscoverStatus', 'caption'), '3 passende Variablen gefunden (zuletzt 12:00:00 Uhr)') && $upd('Suggestions', 'visible') === true && $upd('AddSuggestions', 'visible') === true, $rD);
$before = $m12->props['Channels']; $m12->fieldUpdates = [];
$rA = $m12->AddSuggestions(json_encode($sug), $before);
$newCh = json_decode((string)$upd('Channels', 'values'), true);
check('Übernehmen: angehakter Vorschlag hängt als Kanal „Lernen + Abspielen" an der Liste IM FORMULAR', is_array($newCh) && count($newCh) === 2 && $newCh[1] === ['Caption' => 'Stehlampe Wohnzimmer', 'TargetID' => 500, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 10], json_encode($newCh));
check('Keine Selbstpersistenz: Eigenschaft unverändert, Meldung verlangt „Übernehmen"', $m12->props['Channels'] === $before && str_contains($rA, '1 Kanal/Kanäle in die Liste übernommen') && str_contains($rA, 'mit „Übernehmen" bestätigen'), $rA);
check('Übernommener Vorschlag verschwindet aus der Vorschlagsliste, die anderen bleiben', array_column(json_decode((string)$upd('Suggestions', 'values'), true), 'TargetID') === [504, 501]);
$m12->fieldUpdates = []; $none = array_map(fn($r) => ['Pick' => false] + $r, $sug);
check('Nichts angehakt: Hinweis, Kanalliste bleibt unberührt', str_contains($m12->AddSuggestions(json_encode($none), $before), 'Erst den Haken') && $upd('Channels', 'values') === null);
check('Unbekannte Variable (manipulierte Zeile) wird nicht übernommen', str_contains($m12->AddSuggestions(json_encode([['Pick' => true, 'TargetID' => 99999, 'Caption' => 'x']]), $before), 'Erst den Haken') && $upd('Channels', 'values') === null);
check('Schon vorhandener Kanal wird nicht doppelt angelegt', str_contains($m12->AddSuggestions(json_encode([['Pick' => true, 'TargetID' => 507]]), $before), 'Erst den Haken'));
$m12->props['Channels'] = json_encode($newCh); $m12->ApplyChanges();
check('Nach „Übernehmen": Schaltverlauf des neuen Kanals kommt sofort aus dem Archiv (10 Tage × an/aus)', count($bufOf(715, 500)) === 20, (string)count($bufOf(715, 500)));
$m12->fieldUpdates = []; $m12->Discover();
check('Erneute Suche: der neue Kanal wird nicht mehr vorgeschlagen', array_column(json_decode((string)$upd('Suggestions', 'values'), true), 'TargetID') === [504, 501]);
$GLOBALS['INSTANCES'][9101] = 'WB 2';
mkvar(508, VARIABLETYPE_BOOLEAN, false, 'Ladefreigabe', '', 9101, true); mkvar(509, VARIABLETYPE_BOOLEAN, false, 'Wallbox aktiv', '', 9101, true); mkvar(510, VARIABLETYPE_BOOLEAN, false, 'Stehlampe Wohnzimmer', '', 1, true); mkvar(511, VARIABLETYPE_BOOLEAN, false, 'Kühlschrank', '', 1, true);
foreach ([508, 509, 510, 511] as $v) { $GLOBALS['ARCHIVE'][$v] = $days(5, [['19:00:00', true], ['22:00:00', false]]); }
$m12->fieldUpdates = []; $m12->Discover(); $caps = array_column(json_decode((string)$upd('Suggestions', 'values'), true), 'Caption', 'TargetID');
check('Eindeutige Bezeichnungen: zwei Variablen desselben Geräts bekommen den Variablennamen dazu', ($caps[508] ?? '') === 'WB 2 · Ladefreigabe' && ($caps[509] ?? '') === 'WB 2 · Wallbox aktiv', json_encode($caps, JSON_UNESCAPED_UNICODE));
check('… ebenso ein Name, den es als Kanal schon gibt', ($caps[510] ?? '') === 'Stehlampe Wohnzimmer #510', json_encode($caps, JSON_UNESCAPED_UNICODE));
check('Kühlgeräte stehen auf der Sperrliste und werden nicht vorgeschlagen', !isset($caps[511]));
$GLOBALS['INSTANCES'][9102] = 'Jalousie Esszimmer'; $GLOBALS['INSTANCES'][9103] = 'Markise'; $GLOBALS['INSTANCES'][9104] = 'Fenster Bad';
mkvar(513, VARIABLETYPE_INTEGER, 0, 'Position', '', 9102, true); $GLOBALS['PROFILES'][513] = ['~Intensity.100', '~Shutter'];
mkvar(514, VARIABLETYPE_INTEGER, 0, 'Position', '', 9103, true); $GLOBALS['PROFILES'][514] = ['~Intensity.100', '~Shutter'];
mkvar(515, VARIABLETYPE_INTEGER, 0, 'Behang', '', 9104, true); $GLOBALS['PROFILES'][515] = ['~Shutter', '~Shutter.Reversed'];
$move = [['07:00:00', 60], ['07:00:05', 30], ['07:00:09', 0], ['20:00:00', 100]]; // Fahrt morgens = drei Zwischenwerte in 9 s
foreach ([513, 514, 515] as $v) { $GLOBALS['ARCHIVE'][$v] = $days(5, $move); }
$m12->fieldUpdates = []; $m12->Discover(); $rows = json_decode((string)$upd('Suggestions', 'values'), true); $by = array_column($rows, null, 'TargetID');
check('Rollläden werden gefunden — am Namen (Jalousie) und allein am Profil (~Shutter) — und sind nicht vor-angehakt', ($by[513]['Kind'] ?? '') === 'Rollladen' && ($by[515]['Kind'] ?? '') === 'Rollladen' && ($by[513]['Pick'] ?? null) === false, json_encode($by[513] ?? null, JSON_UNESCAPED_UNICODE));
check('Eine Fahrt mit Zwischenwerten zählt als EIN Schaltvorgang: 2 je Tag', (float)($by[513]['PerDay'] ?? 0) === 2.0, json_encode($by[513] ?? null));
check('Die Markise steht auf der Sperrliste und wird nicht vorgeschlagen', !isset($by[514]));
check('Reihenfolge: Licht, Dimmer, Rollladen, Sonstiges', array_values(array_unique(array_column($rows, 'Kind'))) === ['Licht', 'Dimmer', 'Rollladen', 'Sonstiges'], json_encode(array_column($rows, 'Kind'), JSON_UNESCAPED_UNICODE));
$GLOBALS['ARCHIVE_INST'] = [];
check('Ohne Archiv: klare Auskunft, nichts bricht', str_contains($m12->Discover(), 'Kein Archiv gefunden'));
$GLOBALS['ARCHIVE'] = [];

heading('26. Meldung aufs Handy: Fehlschlag, Start und Ende (0.3.0)');
$GLOBALS['VISU'] = [8001, 8002]; $GLOBALS['WFC'] = [8003];
$GLOBALS['INSTANCES'][8001] = 'Dietmar'; $GLOBALS['INSTANCES'][8002] = 'Demo'; $GLOBALS['INSTANCES'][8003] = 'Altes WebFront'; $GLOBALS['INSTANCES'][716] = 'Anwesenheit EG';
mkvar(600, VARIABLETYPE_BOOLEAN, false, 'Lampe X', '', 1, true);
clock('2026-10-01 12:00:00');
$m13 = $mk(716, [['Caption' => 'Lampe X', 'TargetID' => 600, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]); $m13->props['MinDayEvents'] = 0; $m13->ApplyChanges();
foreach (['2026-10-05', '2026-10-06'] as $d) { clock("$d 19:00:00"); fire($m13, 600, true); clock("$d 22:00:00"); fire($m13, 600, false); }
$GLOBALS['PUSH'] = []; clock('2026-10-12 12:00:00'); $m13->SetActive(true); $m13->SetActive(false);
check('Vorgabe: keine Meldungen bei Start und Ende', $GLOBALS['PUSH'] === []);
$m13->props['NotifyStartStop'] = true; $m13->ApplyChanges(); $m13->SetActive(true);
check('Start-Meldung an alle Visualisierungen: zwei Kachel-Visualisierungen (Ex-Funktion, Icon und Ton getrennt) und ein WebFront', count($GLOBALS['PUSH']) === 3 && $GLOBALS['PUSH'][0] === ['visu', 8001, 'Anwesenheit EG', '▶️ Simulation gestartet.', 'Bell', 'bell', 0] && $GLOBALS['PUSH'][2][0] === 'wfc' && $GLOBALS['PUSH'][2][1] === 8003, json_encode($GLOBALS['PUSH'], JSON_UNESCAPED_UNICODE));
$m13->SetActive(true);
check('Erneutes „Starten" bei laufender Simulation meldet nicht noch einmal', count($GLOBALS['PUSH']) === 3);
$GLOBALS['PUSH'] = []; $m13->SetActive(false);
check('Ende-Meldung', count($GLOBALS['PUSH']) === 3 && $GLOBALS['PUSH'][0][3] === '⏹ Simulation beendet.', json_encode($GLOBALS['PUSH'], JSON_UNESCAPED_UNICODE));
$m13->props['NotifyTargets'] = json_encode([['Instance' => 8001], ['Instance' => 0], ['Instance' => 600]]); $m13->ApplyChanges();
$GLOBALS['PUSH'] = []; $m13->SetActive(true); $m13->SetActive(false);
check('Zielauswahl: nur die gewählte Visualisierung (leere Zeile und Nicht-Visualisierung zählen nicht)', array_column($GLOBALS['PUSH'], 1) === [8001, 8001], json_encode(array_column($GLOBALS['PUSH'], 1)));
check('Formular nennt die Ziele beim Namen', str_contains(formText(form($m13)), 'Meldungen gehen an: Dietmar.'));
$m13->props['NotifyStartStop'] = false; $m13->props['NotifyFailure'] = true; $m13->ApplyChanges();
$GLOBALS['PUSH'] = []; $GLOBALS['FAIL_RA'] = true; clock('2026-10-12 12:00:00'); $m13->SetActive(true);
clock('2026-10-12 19:00:05'); $m13->Tick(); clock('2026-10-12 22:00:05'); $m13->Tick();
$failPushes = array_values(array_filter($GLOBALS['PUSH'], fn($p) => str_contains($p[3], 'fehlgeschlagen')));
check('Fehlschlag: genau EINE Meldung je Kanal und Tag, obwohl mehrere Schaltungen scheitern', count($failPushes) === 1 && str_contains($failPushes[0][3], 'Schaltung fehlgeschlagen: Lampe X →') && str_contains($failPushes[0][3], 'heute nicht erneut gemeldet'), json_encode($GLOBALS['PUSH'], JSON_UNESCAPED_UNICODE));
clock('2026-10-13 19:00:05'); $m13->Tick();
check('Am nächsten Tag wird wieder gemeldet', count(array_filter($GLOBALS['PUSH'], fn($p) => str_contains($p[3], 'fehlgeschlagen'))) === 2, json_encode(array_column($GLOBALS['PUSH'], 3), JSON_UNESCAPED_UNICODE));
$GLOBALS['FAIL_RA'] = false; $m13->SetActive(false);
$GLOBALS['PUSH'] = [];
check('Probemeldung: Rückmeldung nennt Erfolg und Zahl der Ziele', $m13->TestNotification() === '✅ Probemeldung an 1 von 1 Visualisierung(en) gesendet.' && count($GLOBALS['PUSH']) === 1 && str_contains($GLOBALS['PUSH'][0][3], 'Probemeldung'), $m13->TestNotification());
$GLOBALS['FAIL_PUSH'] = true; $logN = count($GLOBALS['LOG']); $rT = $m13->TestNotification(); $GLOBALS['FAIL_PUSH'] = false;
check('Zustellung scheitert: ehrliche Rückmeldung und Eintrag im Meldungsprotokoll', str_contains($rT, 'nur an 0 von 1') && count($GLOBALS['LOG']) === $logN + 1 && str_contains(end($GLOBALS['LOG']), 'Meldung an „Dietmar" konnte nicht zugestellt werden'), $rT . ' / ' . end($GLOBALS['LOG']));
$GLOBALS['INSTANCES'][716] = 'Anwesenheitssimulation Obergeschoss – Ferienwohnung Süd'; $GLOBALS['PUSH'] = []; $m13->TestNotification();
check('Langer Instanzname: Titel auf 32 Bytes gekürzt, kein zerschnittenes Zeichen', strlen($GLOBALS['PUSH'][0][2]) <= 32 && mb_check_encoding($GLOBALS['PUSH'][0][2], 'UTF-8') && str_starts_with($GLOBALS['PUSH'][0][2], 'Anwesenheitssimulation Oberg'), $GLOBALS['PUSH'][0][2]);
$GLOBALS['VISU'] = []; $GLOBALS['WFC'] = [];
check('Ohne Visualisierung: klare Auskunft im Formular und bei der Probemeldung, nichts bricht', str_contains($m13->TestNotification(), 'Keine passende Visualisierung gefunden') && str_contains(formText(form($m13)), 'Keine passende Visualisierung gefunden'));

heading('27. Dieselbe Zielvariable in zwei Kanälen (0.3.0)');
mkvar(610, VARIABLETYPE_BOOLEAN, false, 'Doppelt', '', 1, true);
clock('2026-10-01 12:00:00');
$m14 = $mk(717, [['Caption' => 'Sofa', 'TargetID' => 610, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0], ['Caption' => 'Sofa zwei', 'TargetID' => 610, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 30]]); $m14->props['MinDayEvents'] = 0; $m14->ApplyChanges();
foreach (['2026-10-05', '2026-10-06'] as $d) { clock("$d 19:00:00"); fire($m14, 610, true); clock("$d 22:00:00"); fire($m14, 610, false); }
check('Kanalübersicht warnt und nennt beide Zeilen', str_contains(formText(form($m14)), '⚠️ Sofa zwei: dieselbe Zielvariable wie „Sofa') && str_contains(formText(form($m14)), 'diese Zeile wird ignoriert'), formText(form($m14)));
clock('2026-10-12 12:00:00'); $n = count($GLOBALS['ACTIONS']); $m14->SetActive(true); clock('2026-10-12 19:00:05'); $m14->Tick(); clock('2026-10-12 22:00:05'); $m14->Tick();
check('Geschaltet wird nur einmal je Schaltvorgang (die zweite Zeile spielt nicht mit)', array_map(fn($a) => $a[2], actionsFrom($n)) === [true, false], json_encode(actionsFrom($n)));
check('Vorschau zeigt den Kanal nur einmal', substr_count($m14->Preview(), 'Vorbild') === 1, $m14->Preview());
$m14->SetActive(false);

heading('28. Vorbild-Tage je Wochentag in der Kanalübersicht (0.3.0)');
mkvar(620, VARIABLETYPE_BOOLEAN, false, 'Flur', '', 1, true);
clock('2026-05-10 12:00:00');
$m15 = $mk(718, [['Caption' => 'Flur', 'TargetID' => 620, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
// Mo 11.05., Di 12.05., Do 14.05. (Himmelfahrt), Mo 18.05. — heute Di 19.05. (zählt noch nicht)
foreach (['2026-05-11', '2026-05-12', '2026-05-14', '2026-05-18', '2026-05-19'] as $d) { clock("$d 07:00:00"); fire($m15, 620, true); clock("$d 08:00:00"); fire($m15, 620, false); }
clock('2026-05-19 12:00:00');
check('Übersicht je Wochentag, heute zählt nicht mit, fehlende Tage als „–"', str_contains(formText(form($m15)), 'Vorbild-Tage: Mo 2 · Di 1 · Mi – · Do 1 · Fr – · Sa – · So –.'), formText(form($m15)));
$m15->props['HolidayRegion'] = 'BW'; $m15->ApplyChanges();
check('Mit Feiertags-Region zählt Himmelfahrt (Do 14.05.) als Sonntag', str_contains(formText(form($m15)), 'Vorbild-Tage: Mo 2 · Di 1 · Mi – · Do – · Fr – · Sa – · So 1.'), '');

heading('29. Zeitbezug „Sonnenauf- und -untergang" (0.3.3, Wunsch aus dem Forum)');
mkvar(10445, VARIABLETYPE_INTEGER, 0, 'Sonnenaufgang', 'Sunrise', 10443);
$sunOf = function (string $ymd): array { $i = date_sun_info(strtotime("$ymd 12:00:00"), 48.47, 7.94); return [$i['sunrise'], $i['sunset']]; };
$setSun = function (string $ymd) use ($sunOf): void { [$sr, $ss] = $sunOf($ymd); $GLOBALS['VARS'][10445]['value'] = $sr; $GLOBALS['VARS'][10444]['value'] = $ss; };
$at = function (int $ts): void { $GLOBALS['CLOCK'] = $ts; };
mkvar(800, VARIABLETYPE_BOOLEAN, false, 'Hoflicht', '', 1, true);
clock('2026-11-02 12:00:00');
$m16 = $mk(720, [['Caption' => 'Hoflicht', 'TargetID' => 800, 'Mode' => 1, 'TimeRef' => 3, 'JitterMin' => 0]]); $m16->props['MinDayEvents'] = 0; $m16->ApplyChanges();
[$sr9, $ss9] = $sunOf('2026-11-09'); $setSun('2026-11-09');
// Vorbild Mo 09.11.: morgens eine Stunde vor Sonnenaufgang an, 20 min danach aus; abends 10 min nach Untergang an, 4 h später aus.
foreach ([[$sr9 - 3600, true], [$sr9 + 1200, false], [$ss9 + 600, true], [$ss9 + 14400, false]] as [$ts9, $v]) { $at($ts9); fire($m16, 800, $v); }
[$sr23, $ss23] = $sunOf('2026-11-23'); $setSun('2026-11-23'); clock('2026-11-23 03:00:00');
$want = date('H:i', $sr23 - 3600) . ' An, ' . date('H:i', $sr23 + 1200) . ' Aus, ' . date('H:i', $ss23 + 600) . ' An, ' . date('H:i', $ss23 + 14400) . ' Aus';
check('Zwei Wochen später: Morgen-Schaltungen hängen am Sonnenaufgang, Abend-Schaltungen am Sonnenuntergang', str_contains($m16->Preview(), $want), $m16->Preview() . ' / erwartet ' . $want);
$m16->props['Channels'] = json_encode([['Caption' => 'Hoflicht', 'TargetID' => 800, 'Mode' => 1, 'TimeRef' => 1, 'JitterMin' => 0]]); $m16->ApplyChanges();
check('Zum Vergleich „nur Sonnenuntergang": die Morgen-Schaltung läge dann woanders', !str_contains($m16->Preview(), date('H:i', $sr23 - 3600) . ' An') && str_contains($m16->Preview(), date('H:i', $ss23 + 600) . ' An'), $m16->Preview());
// Nachts gestartet: Die Abend-Schaltung gehört zum HEUTIGEN Sonnenuntergang, nicht zum gestrigen (der um 03:00 näher liegt).
$GLOBALS['VARS'][800]['value'] = false; $n = count($GLOBALS['ACTIONS']); $m16->SetActive(true);
$at($ss23 + 600 - 1); $m16->Tick(); $early = count(array_filter(actionsFrom($n), fn($a) => $a[2] === true && $a[0] > $ss23));
$at($ss23 + 600); $m16->Tick();
check('Um 03:00 gestartet: die Abend-Schaltung kommt heute 10 min nach Sonnenuntergang (nicht als „schon vorbei" verworfen)', $early === 0 && count(array_filter(actionsFrom($n), fn($a) => $a[0] === $ss23 + 600 && $a[2] === true)) === 1, json_encode(actionsFrom($n)));
$m16->SetActive(false); clock('2026-11-23 03:00:00');
check('Auswahl im Formular vorhanden, Kanalliste wächst mit (mindestens 8 Zeilen)', str_contains(formText(form($m16)), '"caption":"Sonnenauf- und -untergang","value":3') && str_contains(formText(form($m16)), '"caption":"Außenhelligkeit","value":4') && str_contains(formText(form($m16)), '"name":"Channels","caption":"Kanäle","rowCount":8'), '');
$many = []; for ($k = 0; $k < 30; $k++) { $many[] = ['Caption' => 'K' . $k, 'TargetID' => 800, 'Mode' => 2, 'TimeRef' => 0, 'JitterMin' => 0]; }
$m16->props['Channels'] = json_encode($many); $m16->ApplyChanges();
check('Viele Kanäle: Liste zeigt bis zu 25 Zeilen (Wunsch aus dem Forum: Fenster war zu niedrig)', str_contains(formText(form($m16)), '"name":"Channels","caption":"Kanäle","rowCount":25'));

heading('30. Zeitbezug „Außenhelligkeit": gemessene Dämmerung statt Sonnenstand (0.3.3)');
$GLOBALS['ARCHIVE'] = []; $GLOBALS['ARCHIVE_INST'] = [53244];
$GLOBALS['INSTANCES'][9200] = 'Wetterstation'; $GLOBALS['INSTANCES'][9201] = 'Sensor Flur'; $GLOBALS['INSTANCES'][9202] = 'WB 1';
mkvar(810, VARIABLETYPE_FLOAT, 500.0, 'Solar Einstrahlung', '', 9200, false); $GLOBALS['PROFILES'][810] = ['~Illumination', ''];
mkvar(811, VARIABLETYPE_FLOAT, 20.0, 'Sensor Multilevel (Beleuchtung)', '', 9201, false); $GLOBALS['PROFILES'][811] = ['Illumination', ''];
mkvar(812, VARIABLETYPE_INTEGER, 255, 'LED Helligkeit', '', 9202, true); $GLOBALS['PROFILES'][812] = ['~Intensity.255', ''];
mkvar(820, VARIABLETYPE_BOOLEAN, false, 'Terrassenlicht', '', 1, true); mkvar(821, VARIABLETYPE_BOOLEAN, false, 'Flurlampe', '', 1, true);
// Sensor-Archiv eines klaren Tages: abends 20 min vor Sonnenuntergang unter 50, morgens 10 min nach Sonnenaufgang über 50.
// Mit der 10-Minuten-Regel: gemessene Abenddämmerung = Untergang - 10 min, Morgendämmerung = Aufgang + 20 min.
$clearDay = function (string $ymd, bool $evening = true) use ($sunOf): void { [$sr, $ss] = $sunOf($ymd);
    for ($x = $sr - 5400; $x <= $sr + 7800; $x += 300) { $GLOBALS['ARCHIVE'][810][] = [$x, $x >= $sr + 600 ? 500.0 : 10.0]; }
    if ($evening) { for ($x = $ss - 7200; $x <= $ss + 6000; $x += 300) { $GLOBALS['ARCHIVE'][810][] = [$x, $x >= $ss - 1200 ? 10.0 : 500.0]; } } };
$GLOBALS['ARCHIVE'][821] = []; $GLOBALS['ARCHIVE'][811] = [];
clock('2026-11-02 12:00:00');
$m17 = $mk(721, [['Caption' => 'Terrasse', 'TargetID' => 820, 'Mode' => 1, 'TimeRef' => 4, 'JitterMin' => 0], ['Caption' => 'Flurlampe', 'TargetID' => 821, 'Mode' => 1, 'TimeRef' => 4, 'JitterMin' => 0]]); $m17->props['MinDayEvents'] = 0; $m17->ApplyChanges();
$m17->fieldUpdates = []; $rF = $m17->FindBrightness();
$picked = null; foreach ($m17->fieldUpdates as $u) { if ($u[0] === 'BrightnessID' && $u[1] === 'value') { $picked = $u[2]; } }
check('Sensorsuche trägt die Wetterstation ein — nicht den Innenraum-Melder, nicht die LED-Helligkeit der Wallbox', $picked === 810 && str_contains($rF, '2 Sensor(en) gefunden') && str_contains($rF, 'Wetterstation') && !str_contains($rF, 'LED') && $m17->props['BrightnessID'] === 0, $rF);
$m17->props['BrightnessID'] = 810; $m17->props['BrightnessDusk'] = 50.0; $m17->props['BrightnessDawn'] = 50.0; $m17->ApplyChanges();
check('Sensor wird beobachtet (Nachricht registriert), Formular zeigt die Untergruppe mit Statuszeile', isset($m17->messages[810]) && str_contains(formText(form($m17)), '🌤  Außenhelligkeit (optional)') && str_contains(formText(form($m17)), 'Sensor jetzt:'));
// Vorbild Mo 09.11.: Terrasse 5 min NACH der gemessenen Dämmerung an, Flurlampe 15 min DAVOR; beide 4 h später aus.
$clearDay('2026-11-09'); $setSun('2026-11-09'); $dusk9 = $ss9 - 600; $dawn9 = $sr9 + 1200;
$at($sr9 - 3600); fire($m17, 820, true); $at($dawn9 + 600); fire($m17, 820, false);
$at($dusk9 - 900); fire($m17, 821, true); $at($dusk9 + 300); fire($m17, 820, true); $at($dusk9 + 14400); fire($m17, 820, false); fire($m17, 821, false);
// Heute Mo 23.11.: dunkle Wolken, es wird schon eine Stunde vor Sonnenuntergang dunkel.
$clearDay('2026-11-23', false); $setSun('2026-11-23'); $GLOBALS['VARS'][810]['value'] = 500.0;
clock('2026-11-23 12:00:00'); $m17->SetActive(true);
$usual = $ss23 - 600; // üblich: wie am Vorbild-Tag 10 min vor Sonnenuntergang
check('Vor der Dämmerung plant das Modul mit der üblichen Zeit: Terrasse an um Untergang - 5 min', str_contains($m17->Preview(), date('H:i', $usual + 300) . ' An'), $m17->Preview());
$n = count($GLOBALS['ACTIONS']); $t1 = $ss23 - 3600;
$at($t1); fire($m17, 810, 10.0); $at($t1 + 300); fire($m17, 810, 10.0);
check('Sensor erst 5 min dunkel: noch nichts geschaltet (10-Minuten-Regel)', actionsFrom($n) === [], json_encode(actionsFrom($n)));
$at($t1 + 600); fire($m17, 810, 10.0);
check('Nach 10 min Dunkelheit gilt die Dämmerung: die Flurlampe (15 min VOR der Dämmerung) wird sofort nachgeholt', array_map(fn($a) => [$a[0], $a[1], $a[2]], actionsFrom($n)) === [[$t1 + 600, 821, true]], json_encode(actionsFrom($n)));
check('… und „Nächste Schaltung" zeigt die Terrasse 5 min nach der gemessenen Dämmerung', str_contains($m17->GetValue('NextAction'), date('H:i', $t1 + 900) . ' Uhr: Terrasse → An'), $m17->GetValue('NextAction'));
$at($t1 + 900); $m17->Tick();
check('Terrasse geht 5 min nach der gemessenen Dämmerung an — 40 min früher als an einem klaren Tag', array_map(fn($a) => [$a[0], $a[1], $a[2]], actionsFrom($n)) === [[$t1 + 600, 821, true], [$t1 + 900, 820, true]] && ($usual + 300) - ($t1 + 900) === 2400, json_encode(actionsFrom($n)));
$tw = json_decode(GetValueString(IPS_GetObjectIDByIdent('Twilight', 721)), true);
check('Gemessene Dämmerung beider Tage steht im Speicher (heute live, Vorbild-Tag aus dem Sensor-Archiv)', ($tw['2026-11-23'][1] ?? 0) === $t1 + 600 && ($tw['2026-11-09'] ?? null) === [$dawn9, $dusk9], json_encode($tw));
$m17->SetActive(false);
// Mo 30.11.: strahlend klarer Abend, es bleibt länger hell als üblich.
[$sr30, $ss30] = $sunOf('2026-11-30'); $setSun('2026-11-30'); $GLOBALS['VARS'][810]['value'] = 500.0; $GLOBALS['VARS'][820]['value'] = false; $GLOBALS['VARS'][821]['value'] = false;
clock('2026-11-30 12:00:00'); $m17->SetActive(true); $n = count($GLOBALS['ACTIONS']); $usual30 = $ss30 - 600;
$at($usual30 - 900); $m17->Tick();
check('Flurlampe (15 min vor der Dämmerung) kommt zur üblichen Zeit, solange nichts gemessen ist', array_map(fn($a) => [$a[0], $a[1], $a[2]], actionsFrom($n)) === [[$usual30 - 900, 821, true]], json_encode(actionsFrom($n)));
$at($usual30 + 300); $m17->Tick();
check('Übliche Zeit vorbei, aber noch hell: die Terrasse bleibt aus und wartet auf die Dämmerung', count(actionsFrom($n)) === 1, json_encode(actionsFrom($n)));
$at($ss30 + 1200); fire($m17, 810, 10.0); $at($ss30 + 1500); fire($m17, 810, 10.0); $at($ss30 + 1800); fire($m17, 810, 10.0);
check('… die Flurlampe wird dabei NICHT ein zweites Mal geschaltet (sie war schon dran)', count(actionsFrom($n)) === 1, json_encode(actionsFrom($n)));
$at($ss30 + 2100); $m17->Tick();
check('Dämmerung 30 min nach Sonnenuntergang gemessen: Terrasse geht 5 min danach an — 40 min später als üblich', array_map(fn($a) => [$a[0], $a[1], $a[2]], array_slice(actionsFrom($n), 1)) === [[$ss30 + 2100, 820, true]] && ($ss30 + 2100) - ($usual30 + 300) === 2400, json_encode(actionsFrom($n)));
$m17->SetActive(false);
// Mo 07.12.: Sensor ausgefallen (meldet weiter „hell") — spätestens am Ende des Fensters (90 min nach Untergang) wird geschaltet.
[$sr7, $ss7] = $sunOf('2026-12-07'); $setSun('2026-12-07'); $GLOBALS['VARS'][810]['value'] = 500.0; $GLOBALS['VARS'][820]['value'] = false;
clock('2026-12-07 12:00:00'); $m17->SetActive(true); $n = count($GLOBALS['ACTIONS']);
$at($ss7 + 5400 + 300 - 1); $m17->Tick(); $before = count(array_filter(actionsFrom($n), fn($a) => $a[1] === 820));
$at($ss7 + 5400 + 300); $m17->Tick();
check('Sensor meldet nie „dunkel": die Terrasse geht am Ende des Fensters trotzdem an (kein dunkles Haus)', $before === 0 && count(array_filter(actionsFrom($n), fn($a) => $a[1] === 820 && $a[2] === true)) === 1, json_encode(actionsFrom($n)));
$m17->SetActive(false);
// Beim Lernen läuft die Messung mit, damit künftige Vorbild-Tage ihre Dämmerung kennen.
[$sr14, $ss14] = $sunOf('2026-12-14'); $GLOBALS['VARS'][810]['value'] = 500.0;
$at($ss14 - 1800); fire($m17, 810, 30.0); $at($ss14 - 1500); fire($m17, 810, 60.0); $at($ss14 - 1200); fire($m17, 810, 30.0); $at($ss14 - 900); fire($m17, 810, 20.0); $at($ss14 - 600); fire($m17, 810, 10.0);
$tw = json_decode(GetValueString(IPS_GetObjectIDByIdent('Twilight', 721)), true);
check('Auch ohne Simulation gemessen; ein kurzes Wieder-hell-Werden setzt die 10 Minuten neu an', ($tw['2026-12-14'][1] ?? 0) === $ss14 - 600, json_encode($tw['2026-12-14'] ?? null));
$at(strtotime('2026-12-14 12:30:00')); fire($m17, 810, 5.0); $at(strtotime('2026-12-14 12:45:00')); fire($m17, 810, 5.0);
check('Ein dunkles Gewitter am Mittag ist keine Dämmerung (außerhalb des Fensters um den Sonnenuntergang)', (json_decode(GetValueString(IPS_GetObjectIDByIdent('Twilight', 721)), true)['2026-12-14'][1] ?? 0) === $ss14 - 600);
// Schwellen vorschlagen: üblicher Wert eine halbe Stunde vor Sonnenuntergang bzw. nach Sonnenaufgang.
clock('2026-12-20 12:00:00'); $GLOBALS['ARCHIVE'][810] = [];
for ($d = 1; $d <= 14; $d++) { [$srD, $ssD] = $sunOf(date('Y-m-d', strtotime('2026-12-20 -' . $d . ' days'))); $GLOBALS['ARCHIVE'][810][] = [$ssD - 2000, 70.0 + $d]; $GLOBALS['ARCHIVE'][810][] = [$srD + 1500, 40.0 + $d]; }
$m17->fieldUpdates = []; $rS = $m17->SuggestBrightness(810);
$fu = []; foreach ($m17->fieldUpdates as $u) { $fu[$u[0]] = $u[2]; }
check('Schwellen-Vorschlag: Median der letzten 14 Tage, ins Formular eingetragen, nicht gespeichert', ($fu['BrightnessDusk'] ?? null) === 78.0 && ($fu['BrightnessDawn'] ?? null) === 48.0 && str_contains($rS, 'abends 78') && $m17->props['BrightnessDusk'] === 50.0, $rS . ' ' . json_encode($fu));
$GLOBALS['ARCHIVE'][810] = [];
for ($d = 1; $d <= 14; $d++) { [$srD, $ssD] = $sunOf(date('Y-m-d', strtotime('2026-12-20 -' . $d . ' days'))); $GLOBALS['ARCHIVE'][810][] = [$ssD - 2000, 398.7]; $GLOBALS['ARCHIVE'][810][] = [$srD + 1500, $d === 8 ? 1225.4 : ($d < 8 ? 3.44 : 9000.0)]; }
$m17->fieldUpdates = []; $m17->SuggestBrightness(810); $fu = []; foreach ($m17->fieldUpdates as $u) { $fu[$u[0]] = $u[2]; }
check('Vorschlag als glatte Zahl: 398,7 → 400 und 1225,4 → 1200 (keine vorgetäuschte Genauigkeit)', ($fu['BrightnessDusk'] ?? null) === 400.0 && ($fu['BrightnessDawn'] ?? null) === 1200.0, json_encode($fu));
check('Sensor ohne Archiv: klare Auskunft, was man von Hand einträgt', str_contains($m17->SuggestBrightness(812), 'nicht im Archiv geloggt') && str_contains($m17->SuggestBrightness(0), 'zuerst einen Helligkeitssensor'));
// Ohne Sensor verhält sich „Außenhelligkeit" wie „Sonnenauf- und -untergang".
$m17->props['BrightnessID'] = 0; $m17->ApplyChanges(); $setSun('2026-12-21'); clock('2026-12-21 03:00:00'); $pB = $m17->Preview();
$chs = json_decode($m17->props['Channels'], true); foreach ($chs as &$c) { $c['TimeRef'] = 3; } unset($c); $m17->props['Channels'] = json_encode($chs); $m17->ApplyChanges();
$strip = fn(string $p) => preg_replace('/\(Vorbild[^)]*\)/', '', $p);
check('Ohne Sensor: gleicher Ablauf wie mit „Sonnenauf- und -untergang", Formular sagt es', $strip($pB) === $strip($m17->Preview()) && str_contains($pB, ' An') && str_contains(formText(form($m17)), 'Kein Helligkeitssensor gewählt'), $pB . ' /// ' . $m17->Preview());
$GLOBALS['ARCHIVE'] = []; $GLOBALS['ARCHIVE_INST'] = [];

heading('31. Beschriftungen höchstens 40 Zeichen, Erklärung direkt dahinter — Deutsch und Englisch (SUITE.md, 17.11.2026)');
$measure = function (array $form): array { $long = []; $loose = []; $w = function (array $items) use (&$w, &$long, &$loose) {
    foreach ($items as $k => $e) {
        if (!is_array($e)) { continue; }
        if (isset($e['name'], $e['caption']) && in_array($e['type'] ?? '', ['SelectVariable', 'ValidationTextBox', 'CheckBox', 'NumberSpinner', 'Select', 'SelectInstance'], true) && mb_strlen($e['caption']) > 40) { $long[] = $e['name'] . ' (' . mb_strlen($e['caption']) . ')'; }
        if (isset($e['name']) && str_ends_with($e['name'], 'Hint') && (($items[$k - 1]['name'] ?? '') !== substr($e['name'], 0, -4))) { $loose[] = $e['name']; }
        if (isset($e['items'])) { $w($e['items']); }
    } }; $w($form['elements']); return [$long, $loose]; };
[$longDe, $looseDe] = $measure(form($m17));
check('Deutsch: kein Eingabefeld mit mehr als 40 Zeichen Beschriftung', $longDe === [], implode(', ', $longDe));
check('Jede Erklärung (…Hint) steht direkt hinter ihrem Feld', $looseDe === [], implode(', ', $looseDe));
$GLOBALS['LANG'] = 'en'; AnwsimTest::resetLanguage(); [$longEn, $looseEn] = $measure(form($m17)); $GLOBALS['LANG'] = 'de'; AnwsimTest::resetLanguage();
check('Englisch: kein Eingabefeld mit mehr als 40 Zeichen Beschriftung', $longEn === [] && $looseEn === [], implode(', ', $longEn));

heading('12. Fehlgeschlagene Schaltung landet dauerhaft im Protokoll (über $this->LogMessage)');
$m->props['Channels'] = json_encode([['Caption' => 'Kaputt', 'TargetID' => 100, 'Mode' => 1, 'TimeRef' => 0, 'JitterMin' => 0]]);
$m->ApplyChanges();
$GLOBALS['VARS'][100]['action'] = true;
// Zielvariable "schlägt fehl": RequestAction liefert false → Modul muss warnen, nicht schweigen.
$GLOBALS['FAIL_RA'] = true;
clock('2026-09-08 12:00:00'); fire($m, 100, true); clock('2026-09-08 12:30:00'); fire($m, 100, false);
clock('2026-09-09 12:05:00'); $m->index = 0; $logBefore = count($GLOBALS['LOG']);
$m->RequestAction('Active', true);
check('Fehlschlag wird per LogMessage mit KL_WARNING protokolliert', count($GLOBALS['LOG']) === $logBefore + 1 && str_contains(end($GLOBALS['LOG']), '[' . KL_WARNING . '] Schaltung fehlgeschlagen'), implode(' | ', array_slice($GLOBALS['LOG'], -2)));
check('LastAction zeigt ⚠️ fehlgeschlagen', str_contains($m->GetValue('LastAction'), '⚠️'), $m->GetValue('LastAction'));
$GLOBALS['FAIL_RA'] = false;
$m->RequestAction('Active', false);

echo "\n" . str_repeat('-', 62) . "\n";
if ($fails === 0) {
    echo "OK — alle Prüfungen bestanden.\n";
    exit(0);
}
echo "FEHLER — $fails Prüfung(en) fehlgeschlagen.\n";
exit(1);
