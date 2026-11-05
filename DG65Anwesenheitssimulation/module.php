<?php

// ===========================================================================
// DG65-Toolkit Anwesenheitssimulation (vormals „Schein", umbenannt 13.10.2026)
// — für JEDE schaltbare Variable, geräteunabhängig. Es erzeugt den
// Lichtschein eines bewohnten Hauses: der Schein trügt.
//
// Klassenname bewusst mit DG65-Vorsatz: Symcons eigenes Modul heißt
// „AnwesenheitsSimulation", PHP-Klassennamen sind case-insensitiv —
// ohne Vorsatz gäbe es „Cannot redeclare class", sobald beide installiert sind.
//
// WIE ES ARBEITET:
//   Lernen  — solange die Simulation AUS ist, zeichnet das Modul jede echte
//             Wertänderung der Zielvariablen auf (per VM_UPDATE-Nachricht,
//             kein Polling): Zeitstempel + Wert, in einem versteckten
//             Ringpuffer je Kanal, begrenzt auf das Lernfenster (Tage).
//   Abspielen — ist die Simulation AN, wählt das Modul pro Tag einen echten
//             „Vorbild-Tag" desselben Wochentags aus dem Gelernten und
//             spielt dessen Schaltfolge zeitversetzt (Jitter) ab. So bleibt
//             die Reihenfolge eines realen Abends erhalten (erst Wohnzimmer,
//             dann Küche, dann alles aus), ohne dass jede Woche exakt
//             dieselbe Uhrzeit fällt. Optional kann jeder Kanal an
//             Sonnenauf-/-untergang statt an die Uhrzeit gekoppelt sein —
//             dafür reicht Symcons eingebaute Location-Control-Instanz.
//   Während die Simulation läuft, wird NICHT gelernt — sonst würde das Modul
//   seine eigenen Wiedergaben als echtes Verhalten mitlernen.
//
// SPRACHE: Alle sichtbaren Texte stehen als englische Quelltexte im Code und
// laufen durch t(); die deutsche Fassung liegt in locale.json (SUITE.md
// Konventionen Punkt 9). t() ermittelt die Sprache einmal pro PHP-Lauf und
// liest die Tabelle selbst, statt Symcons Translate() je Text aufzurufen
// (Stolperstein 24: Translate() liest bei jedem Aufruf locale.json).
//
// Eigenständig: setzt kein anderes Modul voraus (Location Control ist ein
// Symcon-Kernbestandteil, kein Fremdmodul) und wird von keinem
// vorausgesetzt.
// ===========================================================================

class DG65Anwesenheitssimulation extends IPSModule
{
    private const LIBRARY_GUID = '{B4C508FF-14C4-47C2-8EC4-7AF933A3CCD0}';
    private const MODULE_GUID  = '{00F44496-648D-4AE0-9AC8-A6002EFCEF54}';

    // Symcon-Kernmodul "Location Control" — liefert Sonnenauf-/-untergang
    // als Unix-Zeitstempel-Variablen (Idents Sunrise/Sunset), live
    // verifiziert an Symcon 9.0 (08.09.2026).
    private const LOCATION_CONTROL_GUID = '{45E97A63-F870-408A-B259-2933F7EABF74}';

    // Formular-Konvention (SUITE.md "Einheitliche Formular-Optik").
    // "Was ist Neu": Schlüssel = Version ohne Beta-/Build-Suffix, Wert = Zeilen
    // (englische Quelltexte, Deutsch in locale.json). Der Banner zeigt alle
    // Einträge neuer als die zuletzt bestätigte Version (Punkt 1, NEWS_VERSIONS-Muster).
    private const NEWS_VERSIONS = [
        '0.1.0' => [
            '• First release: learns real switching patterns per channel (no Symcon archive needed), replays them by the reference-day principle with random offset, shared reference day for all channels.',
            '• Time reference per channel: clock time or distance to sunrise/sunset (uses the built-in Symcon Location Control instance, if present).',
            '• Start/stop via switch, form button, script (IPS_RequestAction) or trigger variable; "📅 Show today\'s schedule" shows the plan.',
            '• 👋 Purpose introduction at the top of the form (can be dismissed once).',
        ],
        '0.1.1' => [
            '• All visible dates (reference day, status line, channel summary) now in the format DD.MM.YYYY.',
        ],
        '0.1.2' => [
            '• After an update this panel only shows what is new since the last confirmed version.',
        ],
        '0.1.3' => [
            '• Failed switchings now appear in the message log with the instance as sender (clickable).',
        ],
        '0.2.0' => [
            '• The module is now called "DG65-Toolkit Presence Simulation" (formerly "Schein"); script functions start with ANWSIM_ instead of SCHEIN_ (e.g. ANWSIM_Preview). Instances, channels and learned data are kept.',
        ],
        '0.2.1' => [
            '• Learned data is kept even when a target variable is temporarily missing (e.g. while a device module recreates its variables). A learning memory is only deleted when the channel is removed from the list.',
        ],
        '0.2.2' => [
            '• The learning window now also applies to channels that have not been switched for a while: outdated days are neither chosen as reference nor counted in the channel summary.',
        ],
        '0.3.0' => [
            '• 🌍 The presence simulation now speaks English: on an English Symcon the form, messages and variable names appear in English. German remains the reference.',
            '• 📥 Shorter learning phase: if a target variable is logged in the Symcon archive, its switching history is taken over — automatically for new channels, via "📥 Fill learning memory from archive" for existing ones.',
            '• Long absences: the learning window is frozen while the simulation runs, so the learned days no longer run out after four weeks.',
            '• Switching now happens to the second instead of once per minute — short on/off sequences are replayed as they were learned.',
            '• New setting "Minimum switching events per reference day": a day on which hardly anything was switched is no longer chosen as reference.',
            '• 🔎 Suggestions: "Find suitable variables" lists the logged, switchable variables that are switched regularly — tick, add, apply.',
            '• Trigger: number and text variables (e.g. a house mode) can now start the simulation, optionally with a start delay.',
            '• New: choose what happens when the simulation ends — leave everything as it is, restore the state from before the start, or switch everything off.',
            '• New variable "Today\'s schedule" for the visualization: current and next state per channel.',
            '• Public holidays can be replayed like Sundays (choose your region).',
            '• Safety: devices such as toasters, irons, door locks or awnings are never switched (adjustable word list).',
            '• 🔔 Notifications (optional): push to your visualization when a switching fails or when the simulation starts or stops.',
            '• Suggestions now also find dimmers and shutters.',
            '• Channel overview: shows per weekday how many reference days are learned, and warns if a target variable is used twice.',
        ],
    ];
    private const REPO_URL     = 'https://github.com/DG65/Anwesenheitssimulation';
    private const LICENSE_URL  = 'https://github.com/DG65/Anwesenheitssimulation/blob/main/LICENSE';
    private const PAYPAL_URL   = 'https://paypal.me/DietmarGureth';

    // Wiedergabe: der Timer wird auf die nächste Schaltzeit gestellt (sekundengenau), spätestens
    // aber nach HEARTBEAT_S — so wirken verschobene Sonnenzeiten und Tageswechsel zeitnah.
    private const HEARTBEAT_S  = 600;
    private const MAX_PERIODS  = 50;    // gemerkte Simulationszeiten (siehe windowStart())
    // Symcon-Kernmodul Archive Control — Schaltverlauf geloggter Variablen für den Erst-Import.
    private const ARCHIVE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MAX_EVENTS   = 5000;  // Obergrenze je Kanal-Ringpuffer
    private const COALESCE_SEC = 20;    // Wertänderungen innerhalb dieser Spanne gelten als EIN Schaltvorgang (Dimmer-Schieber)

    // Was beim Beenden der Simulation geschieht (Eigenschaft EndAction).
    private const END_NOTHING = 0;
    private const END_RESTORE = 1;
    private const END_OFF     = 2;

    // Geräte, die nie geschaltet werden (Vorgabe der Eigenschaft BlockedWords, vom Nutzer änderbar):
    // Wärmegeräte gehören nicht in eine Anwesenheitssimulation, Kühlgeräte dürfen nie ausgehen, und Schlösser,
    // Tore und Markisen bewegt man nicht, wenn niemand da ist. Das sind Gerätenamen (Daten), keine
    // Oberflächentexte — deshalb deutsch UND englisch in einer Liste, nicht in locale.json.
    private const DEFAULT_BLOCKED = 'Toaster, Bügeleisen, Heizlüfter, Heizstrahler, Heizdecke, Heizkissen, Heizofen, Wasserkocher, Backofen, Kaminofen, Pelletofen, Ofen, Kochfeld, Kochplatte, Herdplatte, Fritteuse, Waffeleisen, Raclette, Mikrowelle, Kaffeemaschine, Lockenstab, Glätteisen, Föhn, Haartrockner, Tauchsieder, Sauna, Kühlschrank, Gefrierschrank, Gefriertruhe, Tiefkühl, Markise, Garagentor, Torantrieb, Türöffner, Türschloss, Türschloß, Verriegelung, Verriegelt, Alarmanlage, Iron, Heater, Kettle, Oven, Stove, Fryer, Hotplate, Microwave, Hair dryer, Coffee machine, Fridge, Freezer, Awning, Garage door, Door lock, DoorLock';
    // Woran die Vorschläge Beleuchtung erkennen (nur für das Vor-Anhaken; Teilwort genügt: "Stehlampe").
    private const LIGHT_WORDS = ['licht', 'lampe', 'leuchte', 'strahler', 'light', 'lamp'];
    // Woran die Vorschläge Rollläden/Jalousien erkennen (Zahl-Variablen; zusätzlich zählt das Profil).
    // Art eines Vorschlags (Schlüssel => englischer Name, Deutsch in locale.json).
    private const KIND_NAMES = ['light' => 'Light', 'dimmer' => 'Dimmer', 'shutter' => 'Shutter', 'other' => 'Other'];
    private const SHUTTER_WORDS = ['rollladen', 'rolladen', 'jalousie', 'raffstore', 'rollo', 'shutter', 'blind'];
    // Push-Ziele: Kachel-Visualisierung und klassisches WebFront (SUITE.md "Push-Benachrichtigung").
    private const VISU_GUID = '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}';
    private const WEBFRONT_GUID = '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}';
    // Länder/Bundesländer für Feiertage (Kürzel => englischer Name, Deutsch in locale.json), '' = keine.
    private const REGIONS = ['' => 'Ignore public holidays', 'BW' => 'Baden-Württemberg', 'BY' => 'Bavaria', 'BE' => 'Berlin', 'BB' => 'Brandenburg',
        'HB' => 'Bremen', 'HH' => 'Hamburg', 'HE' => 'Hesse', 'MV' => 'Mecklenburg-Western Pomerania', 'NI' => 'Lower Saxony',
        'NW' => 'North Rhine-Westphalia', 'RP' => 'Rhineland-Palatinate', 'SL' => 'Saarland', 'SN' => 'Saxony', 'ST' => 'Saxony-Anhalt',
        'SH' => 'Schleswig-Holstein', 'TH' => 'Thuringia', 'AT' => 'Austria', 'CH' => 'Switzerland (nationwide holidays)'];

    // Kanal-Modus (Spalte "Modus")
    private const MODE_OFF        = 0;
    private const MODE_LEARN_PLAY = 1;
    private const MODE_LEARN_ONLY = 2;

    // Zeitbezug je Kanal (Spalte "Zeitbezug")
    private const TIMEREF_CLOCK   = 0;
    private const TIMEREF_SUNSET  = 1;
    private const TIMEREF_SUNRISE = 2;

    // Sprach-Tabelle (siehe t()): null = noch nicht ermittelt, [] = Englisch
    // (Quelltext bleibt), Array = deutsche Tabelle aus locale.json,
    // false = andere Sprache (jeden Text einmal bei Symcon nachfragen und merken).
    /** @var array<string,string>|false|null */
    private static $i18n = null;
    /** @var array<string,string> */
    private static $i18nMemo = [];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Channels', '[]');
        $this->RegisterPropertyInteger('LearnDays', 28);
        $this->RegisterPropertyInteger('TriggerID', 0);
        $this->RegisterPropertyBoolean('TriggerInvert', false);
        $this->RegisterPropertyBoolean('CoherentReplay', true);
        $this->RegisterPropertyInteger('MinDayEvents', 4);
        $this->RegisterPropertyString('TriggerValues', '');
        $this->RegisterPropertyInteger('TriggerDelay', 0);
        $this->RegisterPropertyInteger('EndAction', self::END_NOTHING);
        $this->RegisterPropertyString('HolidayRegion', '');
        $this->RegisterPropertyString('BlockedWords', self::DEFAULT_BLOCKED);
        // Meldungen aufs Handy (beides aus = keine Meldungen). Ziele: Liste von Visualisierungen, leer = alle.
        $this->RegisterPropertyBoolean('NotifyFailure', false);
        $this->RegisterPropertyBoolean('NotifyStartStop', false);
        $this->RegisterPropertyString('NotifyTargets', '[]');

        // Tagesplan der laufenden Simulation (Vorbild-Tag, Jitter, bis
        // wohin schon abgespielt wurde). Bewusst ein Attribut: geht bei
        // einem Modul-Resync verloren — dann wird beim nächsten Tick
        // einfach ein neuer Plan gewählt, nichts Gelerntes ist betroffen
        // (die Ringpuffer sind Variablen, keine Attribute).
        $this->RegisterAttributeString('ReplayPlan', '{}');
        $this->RegisterAttributeInteger('ActiveSince', 0);
        $this->RegisterAttributeInteger('LastLearnTs', 0);
        // Zustand der Kanäle beim Start (für "Zustand von vor dem Start wiederherstellen") und
        // laufende Startverzögerung des Auslösers (Zeitstempel, 0 = keine).
        $this->RegisterAttributeString('StartState', '{}');
        $this->RegisterAttributeInteger('DelayUntil', 0);
        // Stand der Kanäle am Ende der letzten Simulation: Vergleichswert fürs Lernen, damit weder die
        // Endaktion (Ausschalten/Wiederherstellen) als echtes Verhalten gelernt wird noch die erste
        // echte Schaltung nach der Rückkehr als "keine Änderung" verloren geht.
        $this->RegisterAttributeString('EndState', '{}');
        // Je Kanal der Tag der letzten Fehlschlag-Meldung (höchstens eine Meldung je Kanal und Tag).
        $this->RegisterAttributeString('FailNotified', '{}');

        // Dismiss-Zustände der Rahmen-Panels.
        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeString('SeenNews', '');
        $this->RegisterAttributeBoolean('ForumHintGone', false);

        $this->RegisterTimer('Tick', 0, 'ANWSIM_Tick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('StartDelay', 0, 'ANWSIM_TriggerDelayElapsed($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
            return;
        }

        // Alle bisherigen Nachrichten-Registrierungen lösen, danach frisch
        // aufbauen — sonst bleiben gelöschte Kanäle registriert.
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Instanz-Variablen (MaintainVariable ist idempotent, SUITE.md
        // Stolperstein 3). "Simulation aktiv" ist bewusst eine Variable mit
        // Aktion statt einer Property: so lässt sie sich aus WebFront/
        // Kachel, per Ereignis und per IPS_RequestAction() schalten.
        $this->MaintainVariable('Active', $this->t('Simulation active'), VARIABLETYPE_BOOLEAN, '~Switch', 0, true);
        $this->EnableAction('Active');
        $this->MaintainVariable('LastAction', $this->t('Last switching'), VARIABLETYPE_STRING, '', 1, true);
        $this->MaintainVariable('NextAction', $this->t('Next switching'), VARIABLETYPE_STRING, '', 2, true);
        // Übersicht für die Visualisierung (HTML-Tabelle: aktueller und nächster Stand je Kanal).
        $this->MaintainVariable('Schedule', $this->t('Today\'s schedule'), VARIABLETYPE_STRING, '~HTMLBox', 3, true);
        // Zeiten, in denen die Simulation lief (JSON [[von, bis|0], …], 0 = läuft noch). Bewusst eine
        // Variable, kein Attribut: sie muss einen Modul-Resync überleben, sonst liefe das Lernfenster
        // nach langer Abwesenheit leer (siehe windowStart()).
        $periodsNew = !$this->periodsID();
        $this->MaintainVariable('SimPeriods', $this->t('Simulation periods (internal)'), VARIABLETYPE_STRING, '', 99, true);
        if ($periodsNew && ($pid = $this->periodsID())) {
            SetValueString($pid, '[]');
            IPS_SetHidden($pid, true); // nur bei der Neuanlage voreinstellen
        }
        if ($this->isActive()) {
            // Nach einem Modul-Resync fehlt das Attribut "ActiveSince" — aus der gemerkten
            // Simulationszeit wiederherstellen (sonst stünde im Formular "seit 01.01.1970").
            $since = $this->ReadAttributeInteger('ActiveSince');
            if ($since <= 0) {
                foreach ($this->periods() as $e) {
                    if ((int)($e[1] ?? 0) === 0) {
                        $since = (int)($e[0] ?? 0);
                    }
                }
                $since = $since > 0 ? $since : $this->now();
                $this->WriteAttributeInteger('ActiveSince', $since);
            }
            $this->openPeriod($since);
        }

        $channels = $this->channels();
        $validTargets = [];      // Zielvariable existiert gerade → lernen/abspielen
        $configuredTargets = []; // steht in der Kanalliste → Lernspeicher behalten
        foreach ($channels as $ch) {
            $tid = (int)($ch['TargetID'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $configuredTargets[$tid] = true;
            if (!IPS_VariableExists($tid)) {
                continue;
            }
            $validTargets[$tid] = true;
            $isNew = $this->bufferID($tid) === 0;
            $this->ensureBuffer($tid, (string)($ch['Caption'] ?? ''));
            if ((int)($ch['Mode'] ?? self::MODE_OFF) !== self::MODE_OFF) {
                $this->RegisterMessage($tid, VM_UPDATE);
                if ($isNew) {
                    // Neuer Kanal: Schaltverlauf aus dem Archiv übernehmen, falls die Variable dort
                    // geloggt wird — verkürzt die Lernphase. Darf das Übernehmen nie scheitern lassen.
                    try {
                        $n = $this->importFromArchive($tid);
                        $this->SendDebug('Archive', $this->channelName($ch) . ': ' . ($n < 0 ? 'not logged' : $n . ' events imported'), 0);
                    } catch (Throwable $e) {
                        $this->SendDebug('Archive', 'import failed: ' . $e->getMessage(), 0);
                    }
                }
            }
        }

        // Ringpuffer verwaister Kanäle entfernen — aber NUR, wenn die Zeile
        // aus der Kanalliste verschwunden ist (bzw. die Zielvariable
        // gewechselt wurde). Fehlt die Zielvariable bloß vorübergehend
        // (Fremdmodul legt seine Variablen beim Start/Update neu an), bleibt
        // das Gelernte erhalten — "im Zweifel nie löschen" (SUITE.md
        // Store-Review Punkt 17). Ein Wechsel der Bezeichnung lässt den
        // Puffer ohnehin unangetastet (Schlüssel ist die Zielvariable).
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $cid) {
            $ident = IPS_GetObject($cid)['ObjectIdent'] ?? '';
            if (strpos($ident, 'buf_') !== 0) {
                continue;
            }
            $tid = (int)substr($ident, 4);
            if (!isset($configuredTargets[$tid])) {
                $this->UnregisterVariable($ident); // Store-Checkliste 26: nie IPS_DeleteVariable in der Modulklasse
            }
        }

        $trigger = $this->ReadPropertyInteger('TriggerID');
        if ($trigger > 0 && IPS_VariableExists($trigger)) {
            $this->RegisterMessage($trigger, VM_UPDATE);
        }
        // Geänderte Einstellungen: eine laufende Startverzögerung verfällt.
        $this->SetTimerInterval('StartDelay', 0);
        $this->WriteAttributeInteger('DelayUntil', 0);
        $this->updateScheduleView();

        if (count($validTargets) === 0) {
            $this->SetTimerInterval('Tick', 0);
            $this->SetStatus(104);
            return;
        }
        $this->SetStatus(102);
        // Läuft die Simulation, stellt der nächste Durchlauf den Timer selbst auf die nächste Schaltzeit.
        $this->SetTimerInterval('Tick', $this->isActive() ? 1000 : 0);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === IPS_KERNELMESSAGE) {
            if (isset($Data[0]) && $Data[0] === KR_READY) {
                $this->ApplyChanges();
            }
            return;
        }
        if ($Message !== VM_UPDATE) {
            return;
        }
        if ($SenderID === $this->ReadPropertyInteger('TriggerID') && $SenderID > 0) {
            // Auslöser-Variable: Beenden wirkt sofort, der Start auf Wunsch verzögert (kurzer Einkauf).
            if (!$this->triggerWants($Data[0] ?? false)) {
                $this->SetTimerInterval('StartDelay', 0);
                $this->WriteAttributeInteger('DelayUntil', 0);
                if ($this->isActive()) {
                    $this->switchSimulation(false, 'trigger variable');
                }
            } elseif (!$this->isActive()) {
                $delay = max(0, $this->ReadPropertyInteger('TriggerDelay'));
                if ($delay === 0) {
                    $this->switchSimulation(true, 'trigger variable');
                } elseif ($this->ReadAttributeInteger('DelayUntil') <= 0) {
                    // Nur einmal anstoßen: Variablen, die ihren Wert laufend neu schreiben, dürfen die
                    // Verzögerung nicht immer wieder von vorn beginnen lassen.
                    $this->WriteAttributeInteger('DelayUntil', $this->now() + $delay * 60);
                    $this->SetTimerInterval('StartDelay', $delay * 60 * 1000);
                    $this->SendDebug('Trigger', 'start delayed by ' . $delay . ' min', 0);
                }
            }
            return;
        }
        // Nur $Data[0] (neuer Wert) ist verlässlich dokumentiert — ob sich
        // der Wert geändert hat, prüft learn() selbst gegen den Puffer.
        $this->learn((int)$SenderID, $Data[0] ?? null);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Active') {
            $this->switchSimulation((bool)$Value, 'switch');
            return;
        }
        throw new Exception('Unknown action: ' . $Ident);
    }

    // -----------------------------------------------------------------
    // Öffentliche Aktionen (Formular-Buttons, Skripte)
    // -----------------------------------------------------------------

    /** ANWSIM_SetActive($id, bool $active): string — Simulation starten/beenden, mit Ergebnistext. */
    public function SetActive(bool $active): string
    {
        if ($active === $this->isActive()) {
            return $active
                ? $this->t('ℹ️ The simulation is already running (since %s).', date('d.m.Y H:i', $this->ReadAttributeInteger('ActiveSince')))
                : $this->t('ℹ️ The simulation is already off — the module is learning.');
        }
        if ($active && count($this->playableChannels()) === 0) {
            return $this->t('⚠️ No channel is set to "Learn + replay" with a valid target variable — there would be nothing to replay. Create channels first and apply.');
        }
        $this->switchSimulation($active, 'form');
        if (!$active) {
            switch ($this->ReadPropertyInteger('EndAction')) {
                case self::END_RESTORE:
                    return $this->t('⏹ Simulation stopped — the state from before the start has been restored. From now on the module learns again.');
                case self::END_OFF:
                    return $this->t('⏹ Simulation stopped — the replayed channels have been switched off. From now on the module learns again.');
                default:
                    return $this->t('⏹ Simulation stopped — from now on the module learns again. The target variables stay as they are.');
            }
        }
        $plan = $this->plan();
        $lines = [$this->t('▶️ Simulation started.')];
        if (!empty($plan['belowMin'])) {
            $lines[] = $this->belowMinNote();
        }
        foreach ($this->playableChannels() as $ch) {
            $tid = (int)$ch['TargetID'];
            $p = $plan['channels'][$tid] ?? null;
            $lines[] = $p
                ? $this->t('• %1$s: reference day %2$s, offset %3$s', $this->channelName($ch), $this->fmtDay($p['refDate']), $this->fmtJitter((int)$p['jitterSec']))
                : $this->t('• %s: nothing learned yet — stays quiet', $this->channelName($ch));
        }
        return implode("\n", $lines);
    }

    /**
     * ANWSIM_Preview($id): string — zeigt, was heute abgespielt wird
     * (bei laufender Simulation der echte Plan, sonst ein probeweise
     * gewählter Plan, der NICHT gespeichert wird).
     */
    public function Preview(): string
    {
        $now = $this->now();
        $active = $this->isActive();
        // Laufender Plan nur, wenn er von heute ist (nach Resync/Tageswechsel baut ihn erst der nächste Durchlauf).
        $plan = $active ? $this->plan() : [];
        if (($plan['day'] ?? '') !== date('Y-m-d', $now)) {
            $plan = $this->buildPlan($now);
        }
        $channels = $this->playableChannels();
        $blocked = [];
        foreach ($this->channels() as $ch) {
            $word = (int)($ch['Mode'] ?? self::MODE_OFF) === self::MODE_LEARN_PLAY ? $this->blockedWord($ch) : null;
            if ($word !== null) {
                $blocked[] = $this->t('• %1$s: is never switched ("%2$s" is on the list of devices that are not switched).', $this->channelName($ch), $word);
            }
        }
        if (count($channels) === 0) {
            return $this->t('ℹ️ No channel is set to "Learn + replay" — nothing to show.') . ($blocked ? "\n" . implode("\n", $blocked) : '');
        }
        $lines = [$active
            ? $this->t('📅 Today\'s schedule (simulation running since %s):', date('H:i', $this->ReadAttributeInteger('ActiveSince')))
            : $this->t('📅 This is how the presence simulation would replay today (simulation is off, reference day chosen as a trial):')];
        if (!empty($plan['belowMin'])) {
            $lines[] = $this->belowMinNote();
        }
        if ($this->isHoliday(date('Y-m-d', $now))) {
            $lines[] = $this->t('ℹ️ Today is a public holiday — replayed like a Sunday.');
        }
        foreach ($blocked as $line) {
            $lines[] = $line;
        }
        foreach ($channels as $ch) {
            $tid = (int)$ch['TargetID'];
            $p = $plan['channels'][$tid] ?? null;
            if (!$p) {
                $lines[] = $this->t('• %s: nothing learned yet — stays quiet.', $this->channelName($ch));
                continue;
            }
            $steps = [];
            foreach ($this->scheduledEvents($ch, $p, $now) as $ev) {
                $steps[] = date('H:i', $ev['at']) . ' ' . $this->fmtValue($ev['v']);
            }
            $lines[] = $this->t('• %1$s (reference day %2$s, offset %3$s): %4$s', $this->channelName($ch), $this->fmtDay($p['refDate']), $this->fmtJitter((int)$p['jitterSec']),
                $steps ? implode(', ', $steps) : $this->t('no switching on this reference day'));
        }
        return implode("\n", $lines);
    }

    /** ANWSIM_ForgetChannel($id, string $caption): string — Gelerntes eines Kanals verwerfen. */
    public function ForgetChannel(string $caption): string
    {
        foreach ($this->channels() as $ch) {
            if (($ch['Caption'] ?? '') !== $caption) {
                continue;
            }
            $bid = $this->bufferID((int)($ch['TargetID'] ?? 0));
            if (!$bid) {
                return $this->t('ℹ️ Channel "%s" has no learning memory yet — nothing to discard.', $caption);
            }
            SetValueString($bid, '[]');
            return $this->t('🗑 Learned data of "%s" discarded — the channel now starts learning afresh.', $caption);
        }
        return $this->t('⚠️ No channel named "%s" found.', $caption);
    }

    /**
     * ANWSIM_ImportArchive($id): string — füllt je Kanal den Teil des Lernfensters, der vor den selbst
     * gelernten Daten liegt, aus dem Symcon-Archiv (nur für dort geloggte Zielvariablen).
     */
    public function ImportArchive(): string
    {
        $lines = [];
        foreach ($this->channels() as $ch) {
            $tid = (int)($ch['TargetID'] ?? 0);
            if ($tid <= 0 || !IPS_VariableExists($tid) || (int)($ch['Mode'] ?? self::MODE_OFF) === self::MODE_OFF) {
                continue;
            }
            $this->ensureBuffer($tid, (string)($ch['Caption'] ?? ''));
            $name = $this->channelName($ch);
            try {
                $n = $this->importFromArchive($tid);
            } catch (Throwable $e) {
                $lines[] = $this->t('⚠️ %1$s: archive could not be read (%2$s).', $name, $e->getMessage());
                continue;
            }
            if ($n < 0) {
                $lines[] = $this->t('ℹ️ %s: not logged in the archive — keeps learning by itself.', $name);
            } elseif ($n === 0) {
                $lines[] = $this->t('ℹ️ %s: nothing to add (learning window already covered or no older archive data).', $name);
            } else {
                $buf = $this->buffer($tid);
                $lines[] = $this->t('✅ %1$s: %2$s switching event(s) taken from the archive, learned data now reaches back to %3$s.', $name, $n, date('d.m.Y', (int)($buf[0]['t'] ?? $this->now())));
            }
        }
        // Kopfzeile und Kanal-Zusammenfassung gemeinsam auffrischen (SUITE.md Stolperstein 12).
        $this->UpdateFormField('SimStatus', 'caption', $this->simStatusText());
        $this->UpdateFormField('ChannelSummary', 'caption', $this->channelSummaryText());
        if ($lines === []) {
            return $this->t('ℹ️ No active channel with a valid target variable — nothing to import.');
        }
        return $this->t('📥 Archive import:') . "\n" . implode("\n", $lines);
    }

    /** ANWSIM_TestNotification($id): string — schickt eine Probemeldung an die gewählten Visualisierungen. */
    public function TestNotification(): string
    {
        [$sent, $total] = $this->notify($this->t('Test notification — this is how the presence simulation reports.'));
        if ($total === 0) {
            return $this->t('⚠️ No matching visualization found — notifications need a tile visualization or a WebFront.');
        }
        return $sent === $total
            ? $this->t('✅ Test notification sent to %1$s of %2$s visualization(s).', $sent, $total)
            : $this->t('⚠️ Test notification delivered to only %1$s of %2$s visualization(s) — details in the message log.', $sent, $total);
    }

    /** Timer-Einstieg der Startverzögerung: startet nur, wenn der Auslöser immer noch "simulieren" sagt. */
    public function TriggerDelayElapsed()
    {
        $this->SetTimerInterval('StartDelay', 0);
        $this->WriteAttributeInteger('DelayUntil', 0);
        $trigger = $this->ReadPropertyInteger('TriggerID');
        if ($trigger > 0 && IPS_VariableExists($trigger) && !$this->isActive() && $this->triggerWants(GetValue($trigger))) {
            $this->switchSimulation(true, 'trigger variable (delayed)');
        }
    }

    /**
     * ANWSIM_Discover($id): string — sucht geloggte, schaltbare Bool-Variablen, die im Lernfenster
     * regelmäßig geschaltet wurden, und zeigt sie als Vorschläge. Angelegt wird nichts: der Nutzer
     * hakt an, übernimmt in die Kanalliste und bestätigt mit "Übernehmen".
     */
    public function Discover(): string
    {
        $archive = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID)[0] ?? 0;
        if (!$archive) {
            return $this->t('ℹ️ No archive found — suggestions need logged variables. Channels can still be added by hand.');
        }
        $now = $this->now();
        $from = $this->windowStart($now);
        $existing = [];
        foreach ($this->channels() as $ch) {
            $existing[(int)($ch['TargetID'] ?? 0)] = true;
        }
        $rows = [];
        $kinds = [];
        foreach (AC_GetAggregationVariables($archive, false) as $entry) {
            $vid = (int)($entry['VariableID'] ?? 0);
            if ($vid <= 0 || isset($existing[$vid]) || !IPS_VariableExists($vid)) {
                continue;
            }
            if (!HasAction($vid) || IPS_GetParent($vid) === $this->InstanceID) {
                continue;
            }
            $name = $this->suggestionName($vid);
            $own = IPS_GetName($vid);
            // An/Aus-Variablen immer; Zahl-Variablen nur, wenn sie ein Dimmer oder ein Rollladen sind
            // (schaltbare Zahlen sind sonst Sollwerte, Ladeströme, Betriebsarten — nichts für eine Simulation).
            $kind = $this->suggestionKind($vid, $name . ' ' . $own);
            if ($kind === '') {
                continue;
            }
            if ($this->blockedWordIn(array_merge([$name, $own], $this->profileNames($vid))) !== null) {
                continue; // Geräte, die nie geschaltet werden, schlagen wir gar nicht erst vor
            }
            $numeric = (int)IPS_GetVariable($vid)['VariableType'] !== VARIABLETYPE_BOOLEAN;
            $changes = 0;
            $days = [];
            $last = null;
            $lastTs = 0;
            foreach (array_reverse(AC_GetLoggedValues($archive, $vid, $from, $now, 0)) as $row) {
                $value = $numeric ? (float)($row['Value'] ?? 0) : (bool)($row['Value'] ?? false);
                $ts = (int)($row['TimeStamp'] ?? 0);
                if ($last !== null && $value === $last) {
                    continue;
                }
                $last = $value;
                // Schieberegler/Fahrt: mehrere Werte binnen Sekunden sind EIN Schaltvorgang (wie beim Lernen).
                $sameMove = $numeric && $changes > 0 && $ts - $lastTs < self::COALESCE_SEC;
                $lastTs = $ts;
                if ($sameMove) {
                    continue;
                }
                $changes++;
                $days[date('Y-m-d', $ts)] = true;
            }
            $dayCount = count($days);
            if ($dayCount < 3 || $changes / $dayCount > 150) {
                continue; // zu selten geschaltet bzw. kein Schaltverlauf, sondern ein Takt/Signal
            }
            $kinds[$vid] = $kind;
            $rows[] = ['Pick' => $kind === 'light' || $kind === 'dimmer', 'Caption' => $name, 'Kind' => $this->t(self::KIND_NAMES[$kind]), 'Location' => IPS_GetLocation($vid),
                'Days' => $dayCount, 'PerDay' => round($changes / $dayCount, 1), 'TargetID' => $vid];
        }
        $rank = ['light' => 0, 'dimmer' => 1, 'shutter' => 2, 'other' => 3];
        usort($rows, function ($a, $b) use ($kinds, $rank) {
            return [$rank[$kinds[$a['TargetID']]], $b['Days'], $b['PerDay']] <=> [$rank[$kinds[$b['TargetID']]], $a['Days'], $a['PerDay']];
        });
        $rows = array_slice($rows, 0, 80);
        // Bezeichnungen eindeutig machen: zwei Variablen desselben Geräts (oder ein Name, den es als
        // Kanal schon gibt) bekommen den Variablennamen dazu.
        $seen = [];
        foreach ($this->channels() as $ch) {
            $seen[mb_strtolower(trim((string)($ch['Caption'] ?? '')))] = 2;
        }
        foreach ($rows as $row) {
            $key = mb_strtolower($row['Caption']);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
        }
        foreach ($rows as $i => $row) {
            if ($seen[mb_strtolower($row['Caption'])] > 1) {
                $own = IPS_GetName($row['TargetID']);
                $rows[$i]['Caption'] .= $own !== $row['Caption'] ? ' · ' . $own : ' #' . $row['TargetID'];
            }
        }
        $status = count($rows) > 0
            ? $this->t('✅ %1$s suitable variable(s) found (last search %2$s).', count($rows), date('H:i:s', $now))
            : $this->t('⚠️ No suitable variables found (last search %s).', date('H:i:s', $now));
        // Statuszeile und Liste gemeinsam auffrischen (SUITE.md Stolperstein 12).
        $this->UpdateFormField('DiscoverStatus', 'caption', $status);
        $this->UpdateFormField('Suggestions', 'values', json_encode($rows));
        $this->UpdateFormField('Suggestions', 'visible', count($rows) > 0);
        $this->UpdateFormField('AddSuggestions', 'visible', count($rows) > 0);
        return count($rows) > 0
            ? $status . "\n" . $this->t('Ticked are lights and dimmers. Shutters and everything else (e.g. wallbox, vehicle, heat pump) are switchable too — tick them only if the simulation should really switch them. Then click "➕ Add ticked as channels".')
            : $status . "\n" . $this->t('Suggestions need switchable variables (lights, sockets, dimmers, shutters) that are logged in the archive and were switched on at least three days.');
    }

    /**
     * ANWSIM_AddSuggestions($id, $suggestionsJson, $channelsJson): string — hängt die angehakten Vorschläge
     * an die Kanalliste IM OFFENEN FORMULAR an (keine Selbstpersistenz: der Nutzer bestätigt mit "Übernehmen").
     */
    public function AddSuggestions(string $suggestionsJson, string $channelsJson): string
    {
        $suggestions = json_decode($suggestionsJson, true);
        $suggestions = is_array($suggestions) ? $suggestions : [];
        $channels = json_decode($channelsJson, true);
        $channels = is_array($channels) ? array_values($channels) : $this->channels();
        $known = [];
        foreach ($channels as $ch) {
            $known[(int)($ch['TargetID'] ?? 0)] = true;
        }
        $added = 0;
        $rest = [];
        foreach ($suggestions as $row) {
            $vid = (int)($row['TargetID'] ?? 0);
            if (empty($row['Pick']) || $vid <= 0) {
                $rest[] = $row;
                continue;
            }
            if (!isset($known[$vid]) && IPS_VariableExists($vid)) {
                $caption = mb_substr(trim((string)($row['Caption'] ?? '')), 0, 80);
                $channels[] = ['Caption' => $caption !== '' ? $caption : $this->suggestionName($vid), 'TargetID' => $vid, 'Mode' => self::MODE_LEARN_PLAY, 'TimeRef' => self::TIMEREF_CLOCK, 'JitterMin' => 10];
                $known[$vid] = true;
                $added++;
            }
        }
        if ($added === 0) {
            return $this->t('ℹ️ First set the tick ☑︎ for the variables you want to add.');
        }
        $this->UpdateFormField('Channels', 'values', json_encode($channels));
        $this->UpdateFormField('Suggestions', 'values', json_encode($rest));
        return $this->t('✅ %s channel(s) added to the list — not saved yet: confirm with "Apply". Their switching history is then taken over from the archive.', $added);
    }

    /** Timer-Einstieg (nur bei laufender Simulation): schaltet Fälliges und stellt sich auf die nächste Schaltzeit. */
    public function Tick()
    {
        if (!$this->isActive()) {
            $this->SetTimerInterval('Tick', 0);
            return;
        }
        $now = $this->now();
        $plan = $this->plan();
        if (($plan['day'] ?? '') !== date('Y-m-d', $now)) {
            // Tageswechsel: neuer Vorbild-Tag, neuer Versatz, Einschwingen
            // auf den Zustand, den das Vorbild um diese Uhrzeit hatte.
            $plan = $this->buildPlan($now);
            $this->settle($plan, $now);
        }

        $nextAt = null;
        $nextText = '';
        foreach ($this->playableChannels() as $ch) {
            $tid = (int)$ch['TargetID'];
            if (!isset($plan['channels'][$tid])) {
                continue;
            }
            $p = &$plan['channels'][$tid];
            $playedUpTo = (int)($p['playedUpTo'] ?? 0);
            $due = null;
            foreach ($this->scheduledEvents($ch, $p, $now) as $ev) {
                if ($ev['at'] > $playedUpTo && $ev['at'] <= $now) {
                    // Sind mehrere fällig (z. B. nach einem Ausfall von Symcon), zählt nur die letzte —
                    // kein Durchschalten verpasster Zwischenstände.
                    $due = $ev;
                } elseif ($ev['at'] > $now && ($nextAt === null || $ev['at'] < $nextAt)) {
                    $nextAt = $ev['at'];
                    $nextText = $this->t('%1$s: %2$s → %3$s', date('H:i', $ev['at']), $this->channelName($ch), $this->fmtValue($ev['v']));
                }
            }
            if ($due !== null) {
                $this->execute($ch, $due['v'], $p['refDate']);
            }
            $p['playedUpTo'] = $now;
            unset($p);
        }
        $this->WriteAttributeString('ReplayPlan', json_encode($plan));
        $this->setStringIfChanged('NextAction', $nextAt === null ? $this->t('No further switching planned for today') : $nextText);

        // Timer auf die nächste Schaltzeit stellen (sekundengenau), spätestens zum Tageswechsel
        // bzw. nach HEARTBEAT_S.
        $wait = min(strtotime('tomorrow', $now) - $now, self::HEARTBEAT_S);
        if ($nextAt !== null) {
            $wait = min($wait, $nextAt - $now);
        }
        $this->SetTimerInterval('Tick', max(1, $wait) * 1000);
        $this->updateScheduleView();
    }

    // -----------------------------------------------------------------
    // Lernen
    // -----------------------------------------------------------------

    private function learn(int $tid, $value): void
    {
        if ($this->isActive() || $value === null) {
            return; // eigene Wiedergabe nie als echtes Verhalten lernen
        }
        $ch = $this->channelByTarget($tid);
        if ($ch === null || (int)($ch['Mode'] ?? self::MODE_OFF) === self::MODE_OFF) {
            return;
        }
        $bid = $this->bufferID($tid);
        if (!$bid) {
            return;
        }
        $now = $this->now();
        $buffer = json_decode(GetValueString($bid), true);
        if (!is_array($buffer)) {
            $buffer = [];
        }
        $value = $this->normalizeValue($value);
        $count = count($buffer);
        $afterSimulation = false;
        $endState = json_decode($this->ReadAttributeString('EndState'), true);
        if (is_array($endState) && array_key_exists((string)$tid, $endState)) {
            // Erste Meldung nach einer Simulation: Der Puffer kennt nur den Stand VOR der Abwesenheit.
            // Maßgeblich ist, wie die Simulation den Kanal hinterlassen hat.
            if ($this->sameValue($endState[(string)$tid], $value)) {
                return; // Rückmeldung der Endaktion bzw. Schreibvorgang ohne Änderung
            }
            unset($endState[(string)$tid]);
            $this->WriteAttributeString('EndState', json_encode((object)$endState));
            $afterSimulation = true;
        } elseif ($count > 0 && $this->sameValue($buffer[$count - 1]['v'], $value)) {
            return; // Schreibvorgang ohne Wertänderung (früh raus, bevor die Sonnenzeiten gelesen werden)
        }

        $ev = ['t' => $now, 'v' => $value];
        $sunset = $this->anchorTs('Sunset', $now);
        $sunrise = $this->anchorTs('Sunrise', $now);
        if ($sunset !== null) {
            $ev['ss'] = $sunset;
        }
        if ($sunrise !== null) {
            $ev['sr'] = $sunrise;
        }
        $this->appendEvent($buffer, $ev, $afterSimulation);
        if (count($buffer) < $count) {
            SetValueString($bid, json_encode($buffer));
            return; // binnen Sekunden zurück auf den Ausgangswert = nichts passiert
        }
        $buffer = $this->trim($buffer, $now);
        SetValueString($bid, json_encode($buffer));
        $this->WriteAttributeInteger('LastLearnTs', $now);
        // Debug-Ausgaben sind Entwickler-Kanal und bleiben englisch (nicht übersetzt).
        $this->SendDebug('Learn', $this->channelName($ch) . ' -> ' . $this->fmtValue($value) . ' (' . count($buffer) . ' events)', 0);
    }

    private function trim(array $buffer, int $now): array
    {
        $cutoff = $this->windowStart($now);
        $buffer = array_values(array_filter($buffer, function ($e) use ($cutoff) {
            return (int)($e['t'] ?? 0) >= $cutoff;
        }));
        if (count($buffer) > self::MAX_EVENTS) {
            $buffer = array_slice($buffer, -self::MAX_EVENTS);
        }
        return $buffer;
    }

    /**
     * Hängt ein Ereignis an einen zeitlich aufsteigenden Puffer an — dieselbe Regel für Lernen und
     * Archiv-Import: gleicher Wert wie zuvor zählt nicht; Dimmer-Schieber & Co. (mehrere Werte binnen
     * COALESCE_SEC) sind EIN Schaltvorgang, nur der Endwert zählt; binnen Sekunden zurück auf den
     * Ausgangswert = nichts passiert (der Puffer wird dann um einen Eintrag kürzer).
     */
    private function appendEvent(array &$buffer, array $ev, bool $always = false): void
    {
        $count = count($buffer);
        // $always: erste echte Schaltung nach einer Simulation — der letzte Eintrag stammt von vor der
        // Abwesenheit und taugt weder zum Vergleich noch zum Zusammenfassen.
        if ($count > 0 && !$always) {
            $last = $buffer[$count - 1];
            if ($this->sameValue($last['v'], $ev['v'])) {
                return;
            }
            $delta = (int)$ev['t'] - (int)$last['t'];
            if ($delta >= 0 && $delta < self::COALESCE_SEC) {
                array_pop($buffer);
                $count--;
                if ($count > 0 && $this->sameValue($buffer[$count - 1]['v'], $ev['v'])) {
                    return;
                }
            }
        }
        $buffer[] = $ev;
    }

    /**
     * Beginn des Lernfensters zum Zeitpunkt $now: der TAGESANFANG vor LearnDays Tagen — wobei Zeiten,
     * in denen die Simulation lief, nicht mitzählen. Während der Simulation wird nicht gelernt; zählte
     * die Abwesenheit mit, liefe das Fenster nach LearnDays Tagen leer und das Haus bliebe dunkel
     * (nachgestellt 17.11.2026: ab Abwesenheitstag 29 keine Schaltung mehr). So bleibt das Fenster
     * während der Simulation stehen, und nach der Rückkehr sind die Tage vor der Abreise noch da.
     * Tagesanfang statt "jetzt minus N Tage": ein Vorbild-Tag liegt ganz im Fenster oder ganz draußen.
     * Kein +86400-Rechnen für Kalendertage (SUITE.md Stolperstein 18).
     */
    private function windowStart(int $now): int
    {
        $days = max(1, $this->ReadPropertyInteger('LearnDays'));
        $base = strtotime('-' . $days . ' days', $now);
        $periods = $this->periods();
        $start = $base;
        for ($i = 0; $i < 20; $i++) {
            $credit = 0;
            foreach ($periods as $e) {
                $from = max((int)($e[0] ?? 0), $start);
                $to = min((int)($e[1] ?? 0) === 0 ? $now : (int)$e[1], $now);
                if ($to > $from) {
                    $credit += $to - $from;
                }
            }
            if ($base - $credit >= $start) {
                break; // weiter zurück liegt keine zusätzliche Simulationszeit mehr
            }
            $start = $base - $credit;
        }
        return strtotime(date('Y-m-d', $start) . ' 00:00:00');
    }

    private function periodsID(): int
    {
        $pid = @IPS_GetObjectIDByIdent('SimPeriods', $this->InstanceID);
        return $pid ? (int)$pid : 0;
    }

    /** Simulationszeiten [[von, bis|0], …]; 0 = läuft noch. */
    private function periods(): array
    {
        $pid = $this->periodsID();
        $p = $pid ? json_decode(GetValueString($pid), true) : null;
        return is_array($p) ? $p : [];
    }

    private function openPeriod(int $from): void
    {
        $pid = $this->periodsID();
        if (!$pid) {
            return;
        }
        $p = $this->periods();
        foreach ($p as $e) {
            if ((int)($e[1] ?? 0) === 0) {
                return; // läuft bereits
            }
        }
        $p[] = [$from, 0];
        SetValueString($pid, json_encode(array_slice($p, -self::MAX_PERIODS)));
    }

    private function closePeriod(int $to): void
    {
        $pid = $this->periodsID();
        if (!$pid) {
            return;
        }
        $p = $this->periods();
        $changed = false;
        foreach ($p as &$e) {
            if ((int)($e[1] ?? 0) === 0) {
                $e[1] = max((int)($e[0] ?? 0) + 1, $to);
                $changed = true;
            }
        }
        unset($e);
        if ($changed) {
            SetValueString($pid, json_encode($p));
        }
    }

    private function inSimPeriod(int $ts): bool
    {
        foreach ($this->periods() as $e) {
            $to = (int)($e[1] ?? 0);
            if ($ts >= (int)($e[0] ?? 0) && ($to === 0 || $ts <= $to)) {
                return true;
            }
        }
        return false;
    }

    // -----------------------------------------------------------------
    // Archiv-Import
    // -----------------------------------------------------------------

    /**
     * Füllt den Teil des Lernfensters, der VOR den selbst gelernten Daten liegt, aus dem Symcon-Archiv.
     * Eigene Daten bleiben unangetastet (keine Doppelten, nichts wird ersetzt); Zeiten, in denen die
     * Simulation lief, werden übersprungen (das Archiv zeichnet auch die Wiedergabe auf). Die
     * Sonnen-Anker der übernommenen Ereignisse werden aus dem Standort der Location Control berechnet.
     * Rückgabe: Zahl der übernommenen Schaltvorgänge, -1 = Variable wird nicht geloggt / kein Archiv.
     * AC_GetLoggedValues liefert höchstens 10000 Werte, neueste zuerst, je ['TimeStamp', 'Value', …]
     * (live geprüft 17.11.2026, gleiche Aufrufe wie in Symcons eigenem Modul).
     */
    private function importFromArchive(int $tid): int
    {
        $archive = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID)[0] ?? 0;
        if (!$archive || !AC_GetLoggingStatus($archive, $tid)) {
            return -1;
        }
        $now = $this->now();
        $raw = $this->rawBuffer($tid);
        $own = $this->trim($raw, $now);
        $from = $this->windowStart($now);
        $to = count($own) > 0 ? (int)$own[0]['t'] - 1 : $now;
        if ($to <= $from) {
            return 0;
        }
        $values = AC_GetLoggedValues($archive, $tid, $from, $to, 0);
        if (!is_array($values) || count($values) === 0) {
            return 0;
        }
        usort($values, function ($a, $b) {
            return (int)$a['TimeStamp'] <=> (int)$b['TimeStamp'];
        });
        $coords = $this->locationCoordinates();
        $imported = [];
        foreach ($values as $row) {
            $t = (int)($row['TimeStamp'] ?? 0);
            if ($t < $from || $t > $to || $this->inSimPeriod($t)) {
                continue;
            }
            $ev = ['t' => $t, 'v' => $this->normalizeValue($this->castForVariable($tid, $row['Value'] ?? null))];
            if ($coords !== null) {
                foreach (['ss' => 'sunset', 'sr' => 'sunrise'] as $key => $which) {
                    $anchor = $this->sunTime($which, $t, $coords[0], $coords[1]);
                    if ($anchor !== null) {
                        $ev[$key] = $anchor;
                    }
                }
            }
            $this->appendEvent($imported, $ev);
        }
        if (count($imported) === 0) {
            return 0;
        }
        $merged = array_merge($raw, $imported);
        usort($merged, function ($a, $b) {
            return (int)$a['t'] <=> (int)$b['t'];
        });
        if (count($merged) > self::MAX_EVENTS) {
            $merged = array_slice($merged, -self::MAX_EVENTS);
        }
        SetValueString($this->bufferID($tid), json_encode($merged));
        return count($imported);
    }

    /** Breiten-/Längengrad aus der Location Control (Eigenschaft "Location"), null ohne Instanz/Standort. */
    private function locationCoordinates(): ?array
    {
        foreach (IPS_GetInstanceListByModuleID(self::LOCATION_CONTROL_GUID) as $iid) {
            $cfg = json_decode((string)@IPS_GetConfiguration($iid), true);
            $loc = $cfg['Location'] ?? null;
            if (is_string($loc)) {
                $loc = json_decode($loc, true);
            }
            if (is_array($loc) && isset($loc['latitude'], $loc['longitude'])) {
                return [(float)$loc['latitude'], (float)$loc['longitude']];
            }
        }
        return null;
    }

    /**
     * Sonnenauf-/-untergang ('sunrise'/'sunset'), der dem Zeitpunkt $ts am nächsten liegt — für
     * vergangene Tage berechnet (date_sun_info stimmt mit den Werten der Location Control sekundengenau
     * überein, live verglichen 17.11.2026). Gleiche Bedeutung wie anchorTs() beim Lernen.
     */
    private function sunTime(string $which, int $ts, float $lat, float $lon): ?int
    {
        $best = null;
        foreach (['-1 day', '+0 day', '+1 day'] as $shift) {
            $info = @date_sun_info(strtotime($shift, $ts), $lat, $lon);
            $v = is_array($info) ? ($info[$which] ?? null) : null;
            if (is_int($v) && $v > 0 && ($best === null || abs($v - $ts) < abs($best - $ts))) {
                $best = $v;
            }
        }
        return $best;
    }

    // -----------------------------------------------------------------
    // Abspielen
    // -----------------------------------------------------------------

    private function switchSimulation(bool $active, string $source): void
    {
        $wasActive = $this->isActive();
        $this->SetValue('Active', $active);
        if ($active) {
            $now = $this->now();
            if (!$wasActive) {
                $this->WriteAttributeInteger('ActiveSince', $now);
                // Zustand der Kanäle VOR dem ersten Eingriff merken (für die Endaktion "wiederherstellen").
                $state = [];
                foreach ($this->playableChannels() as $ch) {
                    $state[(string)(int)$ch['TargetID']] = $this->normalizeValue(GetValue((int)$ch['TargetID']));
                }
                $this->WriteAttributeString('StartState', json_encode($state));
            }
            $this->openPeriod($now); // ab jetzt steht das Lernfenster still
            $plan = $this->buildPlan($now);
            $this->settle($plan, $now);
            $this->WriteAttributeString('ReplayPlan', json_encode($plan));
            $this->Tick(); // schaltet Fälliges und stellt den Timer auf die nächste Schaltzeit
            if (!$wasActive && $this->ReadPropertyBoolean('NotifyStartStop')) {
                $this->notify($this->t('▶️ Simulation started.'));
            }
        } else {
            if ($wasActive && $this->ReadPropertyBoolean('NotifyStartStop')) {
                $this->notify($this->t('⏹ Simulation stopped.'));
            }
            if ($wasActive) {
                $endState = $this->applyEndAction();
                foreach ($this->channels() as $ch) {
                    $tid = (int)($ch['TargetID'] ?? 0);
                    if ($tid > 0 && IPS_VariableExists($tid) && !array_key_exists((string)$tid, $endState)) {
                        $endState[(string)$tid] = $this->normalizeValue(GetValue($tid));
                    }
                }
                $this->WriteAttributeString('EndState', json_encode((object)$endState));
            }
            $this->closePeriod($this->now());
            $this->WriteAttributeString('ReplayPlan', '{}');
            $this->WriteAttributeString('StartState', '{}');
            $this->SetTimerInterval('Tick', 0);
            $this->setStringIfChanged('NextAction', '');
            $this->updateScheduleView();
        }
        $this->SendDebug('Simulation', ($active ? 'started' : 'stopped') . ' (' . $source . ')', 0);
    }

    /** Endaktion: nichts tun, Zustand von vor dem Start wiederherstellen oder alle abgespielten Kanäle ausschalten. */
    private function applyEndAction(): array
    {
        $switched = []; // Zielvariable => Wert, den die Endaktion gesetzt hat (die Rückmeldung des Geräts kann später kommen)
        $mode = $this->ReadPropertyInteger('EndAction');
        if ($mode !== self::END_RESTORE && $mode !== self::END_OFF) {
            return $switched;
        }
        $before = json_decode($this->ReadAttributeString('StartState'), true);
        $before = is_array($before) ? $before : [];
        $plan = $this->plan();
        foreach ($this->playableChannels() as $ch) {
            $tid = (int)$ch['TargetID'];
            if (!isset($plan['channels'][$tid])) {
                continue; // nichts gelernt = von der Simulation nie angefasst: bleibt, wie es ist
            }
            $current = $this->normalizeValue(GetValue($tid));
            if ($mode === self::END_RESTORE) {
                if (!array_key_exists((string)$tid, $before)) {
                    continue; // Startzustand unbekannt (z. B. nach einem Modul-Resync): lieber nichts schalten
                }
                $target = $before[(string)$tid];
                $why = $this->t('state from before the simulation restored');
            } else {
                if (!is_bool($current) && !is_numeric($current)) {
                    continue;
                }
                $target = is_bool($current) ? false : 0;
                $why = $this->t('switched off at the end of the simulation');
            }
            if (!$this->sameValue($current, $target)) {
                $this->execute($ch, $target, '', $why);
                $switched[(string)$tid] = $target;
            }
        }
        return $switched;
    }

    /**
     * Wählt für heute je Kanal einen Vorbild-Tag + Versatz. Bei
     * "CoherentReplay" bekommen alle Kanäle DENSELBEN Vorbild-Tag und
     * denselben relativen Versatz — so bleibt die Abfolge zwischen den
     * Räumen so glaubwürdig wie an dem echten Tag, an dem sie gelernt wurde.
     */
    private function buildPlan(int $now): array
    {
        $today = date('Y-m-d', $now);
        $coherent = $this->ReadPropertyBoolean('CoherentReplay');
        $r = $this->randomUnit(); // gemeinsamer Versatz-Faktor in [-1, 1]

        $channels = $this->playableChannels();
        $perChannelDays = [];
        $dayCounts = []; // Datum => Schaltvorgänge aller Kanäle zusammen
        foreach ($channels as $ch) {
            $tid = (int)$ch['TargetID'];
            $perChannelDays[$tid] = $this->learnedDays($tid, $today, $dayCounts);
        }

        // Mindestzahl Schaltvorgänge je Vorbild-Tag: ein Tag, an dem kaum geschaltet wurde (niemand
        // zu Hause), soll nicht als Vorbild dienen. Erreicht KEIN gelernter Tag die Mindestzahl, gelten
        // alle Tage — lieber ein dünner Tag als ein dunkles Haus; Vorschau und Start sagen das dazu.
        $min = max(0, $this->ReadPropertyInteger('MinDayEvents'));
        $lively = array_filter($dayCounts, function ($c) use ($min) {
            return $c >= $min;
        });
        $belowMin = $min > 1 && count($dayCounts) > 0 && count($lively) === 0;
        $qualify = function (array $days) use ($lively): array {
            $q = array_intersect_key($days, $lively);
            return count($q) > 0 ? $q : $days;
        };

        $sharedRef = null;
        if ($coherent) {
            $union = [];
            foreach ($perChannelDays as $days) {
                $union = $union + $days; // Datum => Wochentag(N)
            }
            $sharedRef = $this->pickReferenceDay($qualify($union), $now);
        }

        $plan = ['day' => $today, 'belowMin' => $belowMin, 'channels' => []];
        foreach ($channels as $ch) {
            $tid = (int)$ch['TargetID'];
            $days = $perChannelDays[$tid];
            if (count($days) === 0) {
                continue; // noch nichts gelernt → dieser Kanal bleibt still
            }
            $ref = $sharedRef;
            // Kanal kam erst nach dem gemeinsamen Vorbild-Tag dazu (hat dort
            // noch gar nicht gelernt)? Dann eigenes Vorbild, statt fälschlich
            // "war den ganzen Tag still" abzuspielen.
            if ($ref === null || min(array_keys($days)) > $ref) {
                $ref = $this->pickReferenceDay($qualify($days), $now);
            }
            $jitterMin = max(0, (int)($ch['JitterMin'] ?? 0));
            $factor = $coherent ? $r : $this->randomUnit();
            $plan['channels'][$tid] = [
                'refDate'    => $ref,
                'jitterSec'  => (int)round($factor * $jitterMin * 60),
                'playedUpTo' => $now,
            ];
        }
        return $plan;
    }

    /**
     * Alle Kalendertage (außer heute) mit gelernten Ereignissen: 'Y-m-d' => Wochentag 1..7.
     * $dayCounts sammelt dabei je Datum die Zahl der Schaltvorgänge (über alle Aufrufe hinweg).
     */
    private function learnedDays(int $tid, string $today, array &$dayCounts): array
    {
        $days = [];
        foreach ($this->buffer($tid) as $ev) {
            $d = date('Y-m-d', (int)$ev['t']);
            if ($d !== $today) {
                $days[$d] = $this->dayClass((int)$ev['t']);
                $dayCounts[$d] = ($dayCounts[$d] ?? 0) + 1;
            }
        }
        ksort($days);
        return $days;
    }

    /**
     * Vorbild-Tag: bevorzugt derselbe Wochentag, sonst dieselbe Tagesklasse
     * (Werktag/Wochenende), sonst irgendein gelernter Tag. Innerhalb der
     * besten Stufe zufällig — so wiederholt sich nie exakt dieselbe Woche.
     */
    private function pickReferenceDay(array $days, int $now): ?string
    {
        if (count($days) === 0) {
            return null;
        }
        $todayN = $this->dayClass($now);
        $weekend = $todayN >= 6;
        $tiers = [[], [], []];
        foreach ($days as $date => $n) {
            if ($n === $todayN) {
                $tiers[0][] = $date;
            } elseif (($n >= 6) === $weekend) {
                $tiers[1][] = $date;
            } else {
                $tiers[2][] = $date;
            }
        }
        foreach ($tiers as $tier) {
            if (count($tier) > 0) {
                return $tier[$this->randomIndex(count($tier))];
            }
        }
        return null;
    }

    /**
     * Ereignisse des Vorbild-Tags, auf heute übertragen (Uhrzeit bzw.
     * Abstand zu Sonnenauf-/-untergang) und um den Tagesversatz verschoben.
     * Rückgabe: [['at' => ts, 'v' => Wert], ...] aufsteigend nach 'at'.
     */
    private function scheduledEvents(array $ch, array $p, int $now): array
    {
        $tid = (int)$ch['TargetID'];
        $ref = (string)$p['refDate'];
        $jitter = (int)($p['jitterSec'] ?? 0);
        $timeRef = (int)($ch['TimeRef'] ?? self::TIMEREF_CLOCK);
        $anchorKey = $timeRef === self::TIMEREF_SUNSET ? 'ss' : ($timeRef === self::TIMEREF_SUNRISE ? 'sr' : '');
        $anchorToday = $anchorKey !== '' ? $this->anchorTs($timeRef === self::TIMEREF_SUNSET ? 'Sunset' : 'Sunrise', $now) : null;

        $out = [];
        foreach ($this->buffer($tid) as $ev) {
            $t = (int)$ev['t'];
            if (date('Y-m-d', $t) !== $ref) {
                continue;
            }
            if ($anchorToday !== null && isset($ev[$anchorKey])) {
                $at = $anchorToday + ($t - (int)$ev[$anchorKey]);
            } else {
                // Uhrzeit des Vorbilds auf das heutige Kalenderdatum legen —
                // per mktime(), nicht per Sekundenarithmetik (DST-sicher).
                $at = mktime((int)date('G', $t), (int)date('i', $t), (int)date('s', $t), (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
            }
            $out[] = ['at' => $at + $jitter, 'v' => $ev['v']];
        }
        usort($out, function ($a, $b) {
            return $a['at'] <=> $b['at'];
        });
        return $out;
    }

    /**
     * Einschwingen: beim Start (und beim Tageswechsel) den Zustand
     * herstellen, den der Vorbild-Tag um diese Uhrzeit hatte — nur die
     * jeweils LETZTE fällige Schaltung je Kanal, kein Nachspielen des
     * ganzen Tages (das wäre ein sichtbares Flackern).
     */
    private function settle(array &$plan, int $now): void
    {
        foreach ($this->playableChannels() as $ch) {
            $tid = (int)$ch['TargetID'];
            if (!isset($plan['channels'][$tid])) {
                continue;
            }
            $refDate = (string)$plan['channels'][$tid]['refDate'];
            $lastDue = null;
            foreach ($this->scheduledEvents($ch, $plan['channels'][$tid], $now) as $ev) {
                if ($ev['at'] <= $now) {
                    $lastDue = $ev;
                }
            }
            // Noch nichts vom Vorbild-Tag fällig (z. B. kurz nach
            // Mitternacht)? Dann gilt der Zustand, in dem der Vorbild-Tag
            // BEGONNEN hat — die letzte gelernte Schaltung davor (meist das
            // „Aus" vom Vorabend).
            $target = $lastDue !== null ? $lastDue['v'] : $this->stateBefore($tid, $refDate);
            if ($target !== null) {
                $current = $this->normalizeValue(GetValue($tid));
                if (!$this->sameValue($current, $target)) {
                    $this->execute($ch, $target, $refDate);
                }
            }
            $plan['channels'][$tid]['playedUpTo'] = $now;
        }
    }

    /** Wert der letzten gelernten Schaltung VOR Beginn des Tages $ymd (null, wenn keine). */
    private function stateBefore(int $tid, string $ymd)
    {
        $dayStart = strtotime($ymd . ' 00:00:00');
        $value = null;
        $best = 0;
        // Bewusst der ROHspeicher: gesucht ist der Zustand VOR dem
        // Vorbild-Tag — liegt der am Fensterrand, steht das letzte Ereignis
        // davor schon außerhalb des Fensters und wäre im getrimmten Puffer weg.
        foreach ($this->rawBuffer($tid) as $ev) {
            $t = (int)$ev['t'];
            if ($t < $dayStart && $t >= $best) {
                $best = $t;
                $value = $ev['v'];
            }
        }
        return $value;
    }

    private function execute(array $ch, $value, string $refDate, string $why = ''): void
    {
        $tid = (int)$ch['TargetID'];
        if (!IPS_VariableExists($tid)) {
            return;
        }
        $value = $this->castForVariable($tid, $value);
        $label = $this->channelName($ch) . ' → ' . $this->fmtValue($value);
        $ok = false;
        $error = '';
        try {
            if (HasAction($tid)) {
                $ok = (bool)RequestAction($tid, $value);
            } else {
                SetValue($tid, $value);
                $ok = true;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        if (!$ok) {
            // Fehlschlag dauerhaft loggen, nicht nur ins flüchtige Debug
            // (SUITE.md "Sichtbare Rückmeldung", Regel 5).
            // $this->LogMessage statt IPS_LogMessage (SUITE.md Store-Review Punkt 15): die Instanz ist Absender.
            $this->LogMessage($this->t('Switching failed: %1$s (variable #%2$s)%3$s', $label, $tid, $error !== '' ? ' — ' . $error : ''), KL_WARNING);
            $this->setStringIfChanged('LastAction', $this->t('%1$s: ⚠️ %2$s failed', date('H:i:s', $this->now()), $label));
            if ($this->ReadPropertyBoolean('NotifyFailure')) {
                // Eine defekte Lampe schlägt bei jeder Schaltung fehl — je Kanal höchstens eine Meldung am Tag.
                $today = date('Y-m-d', $this->now());
                $seen = json_decode($this->ReadAttributeString('FailNotified'), true);
                $seen = is_array($seen) ? $seen : [];
                if (($seen[(string)$tid] ?? '') !== $today) {
                    foreach ($seen as $key => $day) {
                        if ($day !== $today) {
                            unset($seen[$key]);
                        }
                    }
                    $seen[(string)$tid] = $today;
                    $this->WriteAttributeString('FailNotified', json_encode((object)$seen));
                    $this->notify($this->t('⚠️ Switching failed: %s. Further failures of this channel are not reported again today.', $label));
                }
            }
            return;
        }
        if ($why !== '') {
            $this->SendDebug('End', $label . ' (' . $why . ')', 0);
            $this->setStringIfChanged('LastAction', $this->t('%1$s: %2$s (%3$s)', date('H:i:s', $this->now()), $label, $why));
            return;
        }
        $this->SendDebug('Replay', $label . ' (reference day ' . $this->fmtDay($refDate) . ')', 0);
        $this->setStringIfChanged('LastAction', $this->t('%1$s: %2$s (reference day %3$s)', date('H:i:s', $this->now()), $label, $this->fmtDay($refDate)));
    }

    // -----------------------------------------------------------------
    // Sonnenauf-/-untergang aus Symcons Location Control
    // -----------------------------------------------------------------

    /**
     * Zeitstempel des Sonnenauf-/-untergangs, der dem Zeitpunkt $ts am
     * nächsten liegt. Location Control hält in seinen Variablen immer das
     * NÄCHSTE Ereignis (nach dem heutigen Untergang steht dort schon der
     * morgige) — deshalb auf ±12 h um $ts normalisieren. null ohne
     * Location-Control-Instanz.
     */
    private function anchorTs(string $ident, int $ts): ?int
    {
        $vid = $this->locationVariable($ident);
        if ($vid === 0) {
            return null;
        }
        $v = (int)GetValue($vid);
        if ($v <= 0) {
            return null;
        }
        while ($v - $ts > 43200) {
            $v = strtotime('-1 day', $v);
        }
        while ($ts - $v > 43200) {
            $v = strtotime('+1 day', $v);
        }
        return $v;
    }

    private function locationVariable(string $ident): int
    {
        foreach (IPS_GetInstanceListByModuleID(self::LOCATION_CONTROL_GUID) as $iid) {
            $vid = @IPS_GetObjectIDByIdent($ident, $iid);
            if ($vid && IPS_VariableExists($vid)) {
                return (int)$vid;
            }
        }
        return 0;
    }

    private function hasLocationControl(): bool
    {
        return $this->locationVariable('Sunset') > 0;
    }

    // -----------------------------------------------------------------
    // Sprache
    // -----------------------------------------------------------------

    /**
     * Übersetzt einen englischen Quelltext in die Symcon-Sprache; mit
     * Argumenten wie sprintf (%s, %1$s …). Die Sprache wird einmal pro
     * PHP-Lauf ermittelt (liefert Translate() für den Probe-Text die
     * deutsche Fassung?), danach wird die Tabelle aus locale.json selbst
     * gelesen — Symcons Translate() liest sonst bei JEDEM Aufruf die Datei
     * (SUITE.md Stolperstein 24, Muster AUTOMATText::mitTabelle).
     */
    private function t(string $text, ...$args): string
    {
        if (self::$i18n === null) {
            $probe = 'Simulation active';
            $target = $this->translateRaw($probe);
            if ($target === $probe) {
                self::$i18n = [];
            } else {
                $de = json_decode((string)@file_get_contents(__DIR__ . '/locale.json'), true)['translations']['de'] ?? [];
                self::$i18n = (($de[$probe] ?? null) === $target) ? $de : false;
            }
            self::$i18nMemo[$probe] = $target;
        }
        if (self::$i18n !== false) {
            $out = self::$i18n[$text] ?? $text;
        } else {
            $out = self::$i18nMemo[$text] ?? (self::$i18nMemo[$text] = $this->translateRaw($text));
        }
        if ($args === []) {
            return $out;
        }
        $formatted = @vsprintf($out, $args);
        return $formatted === false ? $out : $formatted;
    }

    /** Symcons Translate() — testbar überschreibbar (Prüfstand ohne Symcon). */
    protected function translateRaw(string $text): string
    {
        return $this->Translate($text);
    }

    /** Nur für den Prüfstand: Sprachwahl vergessen (z. B. Umschalten de ↔ en). */
    protected static function forgetLanguage(): void
    {
        self::$i18n = null;
        self::$i18nMemo = [];
    }

    // -----------------------------------------------------------------
    // Helfer
    // -----------------------------------------------------------------

    /** Sagt der Wert der Auslöser-Variable "simulieren"? Bool: TRUE; sonst: Wert steht in der Liste. Invertierbar. */
    private function triggerWants($value): bool
    {
        $list = [];
        foreach (explode(',', $this->ReadPropertyString('TriggerValues')) as $part) {
            if (trim($part) !== '') {
                $list[] = trim($part);
            }
        }
        if (count($list) === 0) {
            $wanted = is_string($value) ? ($value !== '' && $value !== '0') : (bool)$value;
        } else {
            $current = is_bool($value) ? ($value ? '1' : '0') : trim((string)$value);
            $wanted = false;
            foreach ($list as $want) {
                $same = (is_numeric($current) && is_numeric($want)) ? ((float)$current == (float)$want) : (strcasecmp($current, $want) === 0);
                if ($same) {
                    $wanted = true;
                    break;
                }
            }
        }
        return $this->ReadPropertyBoolean('TriggerInvert') ? !$wanted : $wanted;
    }

    /** Wort der Sperrliste, das auf den Kanal passt (Bezeichnung, Variable oder Gerät), sonst null. */
    private function blockedWord(array $ch): ?string
    {
        $tid = (int)($ch['TargetID'] ?? 0);
        $names = [(string)($ch['Caption'] ?? '')];
        if ($tid > 0 && IPS_VariableExists($tid)) {
            $names[] = IPS_GetName($tid);
            $names[] = $this->suggestionName($tid);
            $names = array_merge($names, $this->profileNames($tid)); // z. B. ein Türschloss-Profil
        }
        return $this->blockedWordIn($names);
    }

    private function blockedWordIn(array $names): ?string
    {
        foreach (explode(',', $this->ReadPropertyString('BlockedWords')) as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            $needle = mb_strtolower($word);
            foreach ($names as $name) {
                $hay = mb_strtolower((string)$name);
                // Lange Wörter passen auch als Wortteil ("Reisebügeleisen"); kurze nur am Wortanfang,
                // sonst sperrte "Heater" das "Theater" und "Iron" das "Environment".
                $hit = mb_strlen($needle) >= 7
                    ? mb_strpos($hay, $needle) !== false
                    : preg_match('/(?<![\p{L}])' . preg_quote($needle, '/') . '/u', $hay) === 1;
                if ($hit) {
                    return $word;
                }
            }
        }
        return null;
    }

    /** Sprechender Name einer Variable: das Gerät (übergeordnete Instanz), sonst die Variable selbst. */
    private function suggestionName(int $vid): string
    {
        $parent = IPS_GetParent($vid);
        if ($parent > 0 && IPS_InstanceExists($parent)) {
            return IPS_GetName($parent);
        }
        return IPS_GetName($vid);
    }

    /** Profilnamen einer Variable (Standard und eigenes), ohne leere. */
    private function profileNames(int $vid): array
    {
        $v = IPS_GetVariable($vid);
        return array_values(array_filter([(string)($v['VariableProfile'] ?? ''), (string)($v['VariableCustomProfile'] ?? '')], function ($p) {
            return $p !== '';
        }));
    }

    /** Art eines Vorschlags: 'light', 'other' (An/Aus), 'dimmer', 'shutter' — '' = nicht vorschlagen. */
    private function suggestionKind(int $vid, string $text): string
    {
        $type = (int)IPS_GetVariable($vid)['VariableType'];
        if ($type === VARIABLETYPE_BOOLEAN) {
            return $this->looksLikeLight($text) ? 'light' : 'other';
        }
        if ($type !== VARIABLETYPE_INTEGER && $type !== VARIABLETYPE_FLOAT) {
            return '';
        }
        $text = mb_strtolower($text);
        $profiles = mb_strtolower(implode(' ', $this->profileNames($vid)));
        foreach (['shutter', 'lamella', 'blind'] as $word) {
            if (mb_strpos($profiles, $word) !== false) {
                return 'shutter';
            }
        }
        foreach (self::SHUTTER_WORDS as $word) {
            if (mb_strpos($text, $word) !== false) {
                return 'shutter';
            }
        }
        return mb_strpos($profiles, 'intensity') !== false || mb_strpos($text, 'dimmer') !== false ? 'dimmer' : '';
    }

    /** Gelernte Vorbild-Tage je Wochentag, z. B. "Mo 4 · Di 4 · … · So –" (Feiertage zählen als Sonntag). */
    private function coverageText(int $tid): string
    {
        $counts = array_fill(1, 7, 0);
        $unused = [];
        foreach ($this->learnedDays($tid, date('Y-m-d', $this->now()), $unused) as $class) {
            $counts[$class]++;
        }
        $names = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $parts = [];
        foreach ($counts as $class => $count) {
            $parts[] = $this->t($names[$class]) . ' ' . ($count > 0 ? $count : '–');
        }
        return implode(' · ', $parts);
    }

    /** Visualisierungen, an die Meldungen gehen: die gewählten, sonst alle (Kachel-Visualisierung und WebFront). */
    private function notifyTargets(): array
    {
        $chosen = [];
        $rows = json_decode($this->ReadPropertyString('NotifyTargets'), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if ((int)($row['Instance'] ?? 0) > 0) {
                $chosen[(int)$row['Instance']] = true;
            }
        }
        $out = [];
        foreach ([[self::VISU_GUID, 'tile'], [self::WEBFRONT_GUID, 'webfront']] as [$guid, $kind]) {
            foreach (@IPS_GetInstanceListByModuleID($guid) ?: [] as $id) {
                if (count($chosen) === 0 || isset($chosen[(int)$id])) {
                    $out[] = ['id' => (int)$id, 'name' => IPS_GetName((int)$id), 'kind' => $kind];
                }
            }
        }
        return $out;
    }

    /**
     * Push an die Visualisierungen. Rückgabe [zugestellt, Ziele]. VISU_PostNotificationEx (6 Parameter),
     * nicht VISU_PostNotification — siehe SUITE.md "Push-Benachrichtigung". Beide Funktionen gehören zu
     * Symcons Kern; der function_exists-Wächter hält das Modul trotzdem ohne sie lauffähig.
     */
    private function notify(string $text): array
    {
        $title = trim(IPS_GetName($this->InstanceID));
        $title = $this->truncateBytes($title !== '' ? $title : $this->t('Presence simulation'), 32);
        $text = $this->truncateBytes($text, 256);
        $targets = $this->notifyTargets();
        $sent = 0;
        foreach ($targets as $target) {
            $ok = false;
            try {
                if ($target['kind'] === 'tile' && function_exists('VISU_PostNotificationEx')) {
                    $ok = (bool)VISU_PostNotificationEx($target['id'], $title, $text, 'Bell', 'bell', 0);
                } elseif ($target['kind'] === 'webfront' && function_exists('WFC_PushNotification')) {
                    $ok = (bool)WFC_PushNotification($target['id'], $title, $text, 'bell', $this->InstanceID);
                }
            } catch (Throwable $e) {
                $ok = false;
            }
            if ($ok) {
                $sent++;
            } else {
                $this->LogMessage($this->t('Notification to "%1$s" could not be delivered: %2$s', $target['name'], $text), KL_WARNING);
            }
        }
        if (count($targets) === 0) {
            $this->LogMessage($this->t('No visualization found — notification not delivered: %s', $text), KL_WARNING);
        }
        $this->SendDebug('Notify', $text . ' -> ' . $sent . '/' . count($targets), 0);
        return [$sent, count($targets)];
    }

    /** Kürzt auf höchstens $max BYTES, ohne ein Mehrbyte-Zeichen zu zerschneiden (Titel 32, Text 256). */
    private function truncateBytes(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }
        $out = '';
        foreach (mb_str_split($text) as $char) {
            if (strlen($out . $char) > $max) {
                break;
            }
            $out .= $char;
        }
        return $out;
    }

    /** Wohin Meldungen gehen (Formular, Untergruppe "Meldungen"). */
    private function notifyStatusText(): string
    {
        $names = array_map(function ($target) {
            return $target['name'];
        }, $this->notifyTargets());
        if (count($names) === 0) {
            return $this->t('⚠️ No matching visualization found — notifications need a tile visualization or a WebFront.');
        }
        return $this->t('ℹ️ Notifications go to: %s.', implode(', ', $names));
    }

    private function looksLikeLight(string $text): bool
    {
        $text = mb_strtolower($text);
        foreach (self::LIGHT_WORDS as $word) {
            if (mb_strpos($text, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Wochentag 1..7 (Mo..So) — ein Feiertag der gewählten Region zählt wie ein Sonntag. */
    private function dayClass(int $ts): int
    {
        return $this->isHoliday(date('Y-m-d', $ts)) ? 7 : (int)date('N', $ts);
    }

    private function isHoliday(string $ymd): bool
    {
        $region = $this->ReadPropertyString('HolidayRegion');
        if ($region === '') {
            return false;
        }
        static $cache = [];
        $year = (int)substr($ymd, 0, 4);
        if (!isset($cache[$region . $year])) {
            $cache[$region . $year] = self::holidaysOfYear($year, $region);
        }
        return isset($cache[$region . $year][$ymd]);
    }

    /**
     * Gesetzliche Feiertage eines Jahres als Menge 'JJJJ-MM-TT' => true — Deutschland je Bundesland,
     * Österreich, Schweiz (bundesweite Tage). Gleiche Regeln wie im DG65-Toolkit Automat
     * (AUTOMATLogik::holiday), bewusst kopiert: jedes Toolkit-Modul läuft für sich allein.
     */
    private static function holidaysOfYear(int $y, string $region): array
    {
        // Ostersonntag (Gauß/Lichtenberg), ohne die calendar-Erweiterung
        $a = $y % 19;
        $b = intdiv($y, 100);
        $c = $y % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv($b - intdiv($b + 8, 25) + 1, 3) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - ($c % 4)) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $em = intdiv($h + $l - 7 * $m + 114, 31);
        $ed = (($h + $l - 7 * $m + 114) % 31) + 1;
        $rel = function (int $offset) use ($y, $em, $ed): string {
            return date('Y-m-d', mktime(12, 0, 0, $em, $ed + $offset, $y));
        };
        $fix = function (int $month, int $day) use ($y): string {
            return sprintf('%04d-%02d-%02d', $y, $month, $day);
        };
        if ($region === 'AT') {
            $list = [$fix(1, 1), $fix(1, 6), $rel(1), $fix(5, 1), $rel(39), $rel(50), $rel(60), $fix(8, 15), $fix(10, 26), $fix(11, 1), $fix(12, 8), $fix(12, 25), $fix(12, 26)];
        } elseif ($region === 'CH') {
            $list = [$fix(1, 1), $rel(-2), $rel(1), $rel(39), $rel(50), $fix(8, 1), $fix(12, 25), $fix(12, 26)];
        } else {
            $list = [$fix(1, 1), $rel(-2), $rel(1), $fix(5, 1), $rel(39), $rel(50), $fix(10, 3), $fix(12, 25), $fix(12, 26)];
            $in = function (string $regions) use ($region): bool {
                return in_array($region, explode(',', $regions), true);
            };
            if ($in('BW,BY,ST')) {
                $list[] = $fix(1, 6);   // Heilige Drei Könige
            }
            if ($in('BE,MV')) {
                $list[] = $fix(3, 8);   // Internationaler Frauentag
            }
            if ($in('BW,BY,HE,NW,RP,SL')) {
                $list[] = $rel(60);     // Fronleichnam
            }
            if ($in('SL')) {
                $list[] = $fix(8, 15);  // Mariä Himmelfahrt
            }
            if ($in('TH')) {
                $list[] = $fix(9, 20);  // Weltkindertag
            }
            if ($in('BB,MV,SN,ST,TH,HB,HH,NI,SH')) {
                $list[] = $fix(10, 31); // Reformationstag
            }
            if ($in('BW,BY,NW,RP,SL')) {
                $list[] = $fix(11, 1);  // Allerheiligen
            }
            if ($in('SN')) {
                // Buß- und Bettag: Mittwoch vor dem 23. November
                $t = mktime(12, 0, 0, 11, 22, $y);
                while ((int)date('N', $t) !== 3) {
                    $t = mktime(12, 0, 0, 11, (int)date('j', $t) - 1, $y);
                }
                $list[] = date('Y-m-d', $t);
            }
        }
        return array_fill_keys($list, true);
    }

    /** Übersicht für die Visualisierung: aktueller und nächster Stand je Kanal als HTML-Tabelle. */
    private function updateScheduleView(): void
    {
        if (!@IPS_GetObjectIDByIdent('Schedule', $this->InstanceID)) {
            return; // vor dem ersten ApplyChanges (Symcon-Start) gibt es die Variable noch nicht
        }
        $esc = function (string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        if (!$this->isActive()) {
            $html = '<div style="padding:6px;opacity:.75">' . $esc($this->t('Simulation is off — the module is learning.')) . '</div>';
        } else {
            $now = $this->now();
            $plan = $this->plan();
            $cell = 'padding:5px;text-align:left';
            $html = '<table style="width:100%;border-collapse:collapse"><tr>';
            foreach ([$this->t('Channel'), $this->t('Reference day'), $this->t('Current state'), $this->t('Since'), $this->t('Next state'), $this->t('At')] as $head) {
                $html .= '<th style="' . $cell . '">' . $esc($head) . '</th>';
            }
            $html .= '</tr>';
            foreach ($this->playableChannels() as $ch) {
                $p = $plan['channels'][(int)$ch['TargetID']] ?? null;
                $cols = [$this->channelName($ch), '–', '–', '–', '–', '–'];
                if ($p) {
                    $cols[1] = $this->fmtDay((string)$p['refDate']);
                    foreach ($this->scheduledEvents($ch, $p, $now) as $ev) {
                        if ($ev['at'] <= $now) {
                            $cols[2] = $this->fmtValue($ev['v']);
                            $cols[3] = date('H:i', $ev['at']);
                        } else {
                            $cols[4] = $this->fmtValue($ev['v']);
                            $cols[5] = date('H:i', $ev['at']);
                            break;
                        }
                    }
                } else {
                    $cols[1] = $this->t('nothing learned yet');
                }
                $html .= '<tr style="border-top:1px solid rgba(128,128,128,.3)">';
                foreach ($cols as $col) {
                    $html .= '<td style="' . $cell . '">' . $esc((string)$col) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
        $this->setStringIfChanged('Schedule', $html);
    }

    private function belowMinNote(): string
    {
        return $this->t('ℹ️ No learned day reaches the minimum of %s switching events — using the days available.', max(0, $this->ReadPropertyInteger('MinDayEvents')));
    }

    /** Testbar überschreibbar (Prüfstand ohne Symcon). */
    protected function now(): int
    {
        return time();
    }

    /** Zufallsfaktor in [-1, 1] — testbar überschreibbar. */
    protected function randomUnit(): float
    {
        return random_int(-1000, 1000) / 1000;
    }

    /** Zufallsindex 0..$count-1 — testbar überschreibbar. */
    protected function randomIndex(int $count): int
    {
        return random_int(0, max(0, $count - 1));
    }

    private function isActive(): bool
    {
        return (bool)$this->GetValue('Active');
    }

    private function plan(): array
    {
        $plan = json_decode($this->ReadAttributeString('ReplayPlan'), true);
        return is_array($plan) ? $plan : [];
    }

    private function channels(): array
    {
        $channels = json_decode($this->ReadPropertyString('Channels'), true);
        return is_array($channels) ? $channels : [];
    }

    /** Kanäle, die abgespielt werden (Modus "Lernen + Abspielen", gültige Zielvariable). */
    private function playableChannels(): array
    {
        $out = [];
        $seen = [];
        foreach ($this->channels() as $ch) {
            $tid = (int)($ch['TargetID'] ?? 0);
            if ($tid > 0 && isset($seen[$tid])) {
                continue; // dieselbe Zielvariable ein zweites Mal: nur die erste Zeile zählt (sonst würde doppelt geschaltet)
            }
            $seen[$tid] = true;
            if ((int)($ch['Mode'] ?? self::MODE_OFF) === self::MODE_LEARN_PLAY && $tid > 0 && IPS_VariableExists($tid)
                && $this->blockedWord($ch) === null) { // Geräte der Sperrliste werden nie geschaltet
                $out[] = $ch;
            }
        }
        return $out;
    }

    private function channelByTarget(int $tid): ?array
    {
        foreach ($this->channels() as $ch) {
            if ((int)($ch['TargetID'] ?? 0) === $tid) {
                return $ch;
            }
        }
        return null;
    }

    private function channelName(array $ch): string
    {
        $caption = trim((string)($ch['Caption'] ?? ''));
        if ($caption !== '') {
            return $caption;
        }
        $tid = (int)($ch['TargetID'] ?? 0);
        return $tid > 0 && IPS_VariableExists($tid) ? IPS_GetName($tid) : $this->t('Channel');
    }

    private function bufferID(int $tid): int
    {
        if ($tid <= 0) {
            return 0;
        }
        $bid = @IPS_GetObjectIDByIdent('buf_' . $tid, $this->InstanceID);
        return $bid ? (int)$bid : 0;
    }

    private function ensureBuffer(int $tid, string $caption): int
    {
        $bid = $this->bufferID($tid);
        if (!$bid) {
            $bid = $this->RegisterVariableString('buf_' . $tid, $this->t('Learned: %s', $caption !== '' ? $caption : IPS_GetName($tid)), '', 100 + $tid % 1000);
            SetValueString($bid, '[]');
            // Sichtbarkeit nur bei der Neuanlage voreinstellen — danach gehört sie dem
            // Nutzer (Store-Review: Objekteigenschaften nur vorbelegen, nie erzwingen).
            IPS_SetHidden($bid, true);
        }
        return $bid;
    }

    /**
     * Gelernte Ereignisse eines Kanals — IMMER auf das Lernfenster
     * zugeschnitten, auch beim Lesen. Getrimmt wird sonst nur beim Lernen
     * (learn()); ein Kanal ohne neue Ereignisse behielte seine veralteten
     * Tage und böte sie weiter als Vorbild an (live gefunden 23.10.2026:
     * „5 Schaltvorgänge seit 13.09." bei einem Fenster ab 25.09.).
     * Nur lesen, nicht zurückschreiben — der nächste Lernvorgang räumt auf.
     */
    private function buffer(int $tid): array
    {
        return $this->trim($this->rawBuffer($tid), $this->now());
    }

    /** Ungetrimmter Lernspeicher — nur für stateBefore(), sonst buffer() verwenden. */
    private function rawBuffer(int $tid): array
    {
        $bid = $this->bufferID($tid);
        if (!$bid) {
            return [];
        }
        $buffer = json_decode(GetValueString($bid), true);
        return is_array($buffer) ? $buffer : [];
    }

    private function normalizeValue($value)
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value + 0;
        }
        return (string)$value;
    }

    private function sameValue($a, $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool)$a === (bool)$b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float)$a - (float)$b) < 0.000001;
        }
        return $a === $b;
    }

    private function castForVariable(int $tid, $value)
    {
        switch ((int)(IPS_GetVariable($tid)['VariableType'] ?? -1)) {
            case VARIABLETYPE_BOOLEAN:
                return (bool)$value;
            case VARIABLETYPE_INTEGER:
                return (int)$value;
            case VARIABLETYPE_FLOAT:
                return (float)$value;
            default:
                return (string)$value;
        }
    }

    private function fmtValue($value): string
    {
        if (is_bool($value)) {
            return $value ? $this->t('On') : $this->t('Off');
        }
        if (is_float($value)) {
            // Dezimaltrennzeichen je Sprache (Deutsch: Komma).
            return rtrim(rtrim(number_format($value, 2, $this->t('decimal point'), ''), '0'), $this->t('decimal point'));
        }
        return (string)$value;
    }

    private function fmtDay(string $ymd): string
    {
        $ts = strtotime($ymd . ' 12:00:00');
        $names = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        // Datum in beiden Sprachen TT.MM.JJJJ (SUITE.md Store-Checkliste 9b, Verbund-Praxis Automat).
        return $this->t($names[(int)date('N', $ts)] ?? '') . ' ' . date('d.m.Y', $ts);
    }

    private function fmtJitter(int $sec): string
    {
        $min = (int)round($sec / 60);
        return $this->t('%s min', ($min >= 0 ? '+' : '') . $min);
    }

    private function setStringIfChanged(string $ident, string $value): void
    {
        if ($this->GetValue($ident) !== $value) {
            $this->SetValue($ident, $value);
        }
    }

    // -----------------------------------------------------------------
    // Formular
    // -----------------------------------------------------------------

    public function AckPurposeIntro(): void
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    public function AckNews(): void
    {
        // Gespeichert wird die tatsächlich INSTALLIERTE Version, nicht der
        // zuletzt gezeigte Eintrag — so zeigt der Banner nach einem Update
        // genau die Lücke seit dieser Bestätigung.
        $this->WriteAttributeString('SeenNews', $this->installedVersion());
        $this->UpdateFormField('NewsPanel', 'visible', false);
    }

    /** Version ohne Beta-/Build-Suffix ("0.1.2-beta.1" → "0.1.2"). */
    private static function baseVersion(string $version): string
    {
        return preg_replace('/-.*$/', '', $version);
    }

    /** Installierte Bibliotheksversion; ohne Kernel-Auskunft der höchste NEWS_VERSIONS-Schlüssel. */
    private function installedVersion(): string
    {
        $lib = @IPS_GetLibrary(self::LIBRARY_GUID);
        if (is_array($lib) && !empty($lib['Version'])) {
            return self::baseVersion((string)$lib['Version']);
        }
        $keys = array_keys(self::NEWS_VERSIONS);
        usort($keys, 'version_compare');
        return (string)end($keys);
    }

    public function AckForumHint(): void
    {
        $this->WriteAttributeBoolean('ForumHintGone', true);
        $this->UpdateFormField('ForumHintPanel', 'visible', false);
    }

    private function PurposeIntro(): ?array
    {
        if ($this->ReadAttributeBoolean('PurposeIntroGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'PurposeIntroPanel', 'expanded' => true,
            'caption' => '👋  What is this module for?',
            'items' => [
                ['type' => 'Label', 'caption' => 'The presence simulation makes an empty house look lived-in. It remembers when you really switch lights, shutters, sockets or the radio in everyday life — any switchable Symcon variable, from any module — and replays this real pattern as soon as nobody is home.'],
                ['type' => 'Label', 'caption' => 'The benefit: no rigid schedules that any observer sees through after two evenings. Each day the module picks a real reference day of the same weekday from the last weeks, shifts it randomly by a few minutes and keeps the order between the rooms believable.'],
                ['type' => 'Label', 'caption' => 'Start via the switch "Simulation active" (also from WebFront/tile or by script) or automatically through an absence/alarm variable. As long as the simulation is off, the module simply keeps learning.'],
                ['type' => 'Button', 'caption' => 'Got it – do not show again', 'onClick' => 'ANWSIM_AckPurposeIntro($id);'],
            ],
        ];
    }

    private function NewsBanner(): ?array
    {
        $seen = $this->ReadAttributeString('SeenNews');
        if ($seen === '') {
            $seen = '0';
        }
        $pending = [];
        foreach (self::NEWS_VERSIONS as $version => $lines) {
            if (version_compare((string)$version, $seen, '>')) {
                $pending[(string)$version] = $lines;
            }
        }
        if (count($pending) === 0) {
            return null;
        }
        uksort($pending, 'version_compare');
        $items = [];
        foreach ($pending as $version => $lines) {
            $items[] = ['type' => 'Label', 'caption' => $this->t('Version %s:', $version)];
            foreach ($lines as $line) {
                $items[] = ['type' => 'Label', 'caption' => $line];
            }
        }
        $items[] = ['type' => 'Button', 'caption' => 'Got it – do not show again', 'onClick' => 'ANWSIM_AckNews($id);'];
        return [
            'type' => 'ExpansionPanel', 'name' => 'NewsPanel', 'expanded' => true,
            'caption' => $this->t('🆕  New up to version %s', array_key_last($pending)),
            'items' => $items,
        ];
    }

    private function DocPanel(): array
    {
        $lib    = @IPS_GetLibrary(self::LIBRARY_GUID);
        $verTxt = (is_array($lib) && isset($lib['Version']))
            ? $this->t('ℹ️ Presence simulation version %1$s (build %2$s)', $lib['Version'], $lib['Build'] ?? '?')
            : 'ℹ️ Presence simulation';
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '📖  Documentation & help',
            'items' => [
                ['type' => 'Label', 'caption' => $verTxt],
                ['type' => 'Label', 'caption' => 'Create a channel: choose a name, the target variable (boolean, integer or float — e.g. a light, a socket, a shutter), mode, time reference and offset, then "Apply". Each channel gets a hidden variable "Learned: …" (the learning memory, no Symcon archive needed).'],
                ['type' => 'Label', 'caption' => 'Learning: as long as "Simulation active" is OFF, the module records every real value change of the target variable — time and value. Everything outside the learning window (default 28 days) drops out automatically, so the pattern follows the season by itself.'],
                ['type' => 'Label', 'caption' => 'Replaying: when the simulation is ON, the module picks for each day a real reference day of the same weekday (otherwise weekday/weekend) and replays its switching sequence — shifted by a random offset up to the configured maximum. At start it first restores the state the reference day had at this time.'],
                ['type' => 'Label', 'caption' => 'Mode "Learn only" collects patterns without ever switching — handy to observe a new channel first ("📅 Show today\'s schedule" shows what it would do). Mode "Off" pauses the channel completely but keeps the learned data.'],
                ['type' => 'Label', 'caption' => 'Time reference "Sunset"/"Sunrise": switching times are learned and replayed as distance to the sun position (lighting then follows dusk, not the clock). Requires the Symcon Location Control instance (core component, Settings → Location) — without it, clock time applies automatically.'],
                ['type' => 'Label', 'caption' => 'Trigger variable (optional): a boolean variable such as "Away" or "Alarm armed" (TRUE starts, FALSE stops), or a number/text variable such as a house mode (the simulation runs while the value is one of the listed values). "Invert" reverses this, a start delay keeps a short errand from starting it. The switch "Simulation active" always stays operable by hand.'],
                ['type' => 'Label', 'caption' => 'Suggestions: "🔎 Find suitable variables" lists logged, switchable variables that are switched regularly. Tick, click "➕ Add ticked as channels" and confirm with "Apply" — their switching history is taken over from the archive right away.'],
                ['type' => 'Label', 'caption' => 'End of the simulation: by default the target variables stay as they are. Optionally the state from before the start is restored, or all replayed channels are switched off. Public holidays of the chosen region are replayed like Sundays. The variable "Today\'s schedule" shows current and next state per channel in the visualization.'],
                ['type' => 'Label', 'caption' => 'Safety: devices whose name contains a word from the list "never switch" (e.g. toaster, iron, door lock, awning) are never switched, whatever is configured.'],
                ['type' => 'Label', 'caption' => 'Notifications (optional): a push to your visualization when a switching fails, or when the simulation starts or stops. The channel overview shows per weekday how many reference days are learned ("–" = none yet; a similar day is used instead).'],
                ['type' => 'Label', 'caption' => 'While the simulation is running, nothing is learned — otherwise the module would learn its own replays as real behaviour. When stopped, the target variables stay as they are.'],
                ['type' => 'Label', 'caption' => 'Scripts: IPS_RequestAction(<InstanceID>, \'Active\', true/false) starts/stops; ANWSIM_Preview(<InstanceID>) returns today\'s schedule as text; ANWSIM_ForgetChannel(<InstanceID>, \'Name\') discards the learned data of one channel.'],
                ['type' => 'Label', 'caption' => 'Shorter learning phase: if a target variable is logged in the Symcon archive, its switching history is taken over for the learning window — automatically for new channels, via "📥 Fill learning memory from archive" for existing ones. Without archive logging the module simply learns by itself.'],
                ['type' => 'Label', 'caption' => 'Long absences: while the simulation is running the learning window stands still, and the time it ran does not count towards the window afterwards — the learned days do not run out, however long you are away. Switching happens to the second, as on the reference day (plus offset).'],
                ['type' => 'Label', 'caption' => 'Several instances are allowed (e.g. per floor or flat). Each instance learns and replays independently.'],
            ],
        ];
    }

    /** Statuszeile des Simulation-Panels (auch für UpdateFormField nach einer Aktion). */
    private function simStatusText(): string
    {
        $channels = $this->channels();
        $playable = count($this->playableChannels());
        $learning = 0;
        $events = 0;
        foreach ($channels as $ch) {
            $tid = (int)($ch['TargetID'] ?? 0);
            if ((int)($ch['Mode'] ?? 0) !== self::MODE_OFF && $tid > 0 && IPS_VariableExists($tid)) {
                $learning++;
            }
            $events += count($this->buffer($tid));
        }
        $lastLearn = $this->ReadAttributeInteger('LastLearnTs');
        if ($this->isActive()) {
            $status = $this->t('🎭 Simulation ACTIVE since %1$s — %2$s channel(s) replaying. Next switching: %3$s', date('d.m.Y H:i', $this->ReadAttributeInteger('ActiveSince')), $playable, $this->GetValue('NextAction') ?: '—');
        } elseif ($learning === 0) {
            $status = 'ℹ️ No channel active yet — create channels below and apply.';
        } else {
            $status = $this->t('💤 Simulation off — %1$s channel(s) learning (%2$s switching event(s) in the learning window%3$s).', $learning, $events,
                $lastLearn > 0 ? $this->t(', last %s', date('d.m.Y H:i:s', $lastLearn)) : $this->t(', none yet'));
        }
        return $status;
    }

    /** Was die Auslöser-Variable gerade sagt (Formular, Untergruppe "Start und Ende"). */
    private function triggerStatusText(): string
    {
        $trigger = $this->ReadPropertyInteger('TriggerID');
        if ($trigger <= 0) {
            return 'ℹ️ No trigger variable chosen — the simulation is only started by hand.';
        }
        if (!IPS_VariableExists($trigger)) {
            return '⚠️ The trigger variable no longer exists.';
        }
        $value = GetValue($trigger);
        $raw = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string)$value;
        return $this->t('ℹ️ Trigger variable now: %1$s (raw value %2$s) → %3$s.', (string)GetValueFormatted($trigger), $raw,
            $this->triggerWants($value) ? $this->t('simulate') : $this->t('do not simulate'));
    }

    private function SimulationPanel(): array
    {
        $status = $this->simStatusText();
        $regions = [];
        foreach (self::REGIONS as $code => $name) {
            $regions[] = ['caption' => $name, 'value' => $code];
        }
        $astro = $this->hasLocationControl()
            ? '✅ Sunrise/sunset available (Symcon Location Control found) — time reference "Sunset"/"Sunrise" can be used.'
            : 'ℹ️ No Location Control instance found — channels with time reference sunrise/sunset fall back to clock time. Create one under Settings → Location if desired.';

        return [
            'type' => 'ExpansionPanel', 'expanded' => true,
            'caption' => '🎭  Simulation',
            'items' => [
                ['type' => 'Label', 'name' => 'SimStatus', 'caption' => $status],
                ['type' => 'RowLayout', 'items' => [
                    ['type' => 'Button', 'caption' => '▶️ Start simulation now', 'onClick' => 'echo ANWSIM_SetActive($id, true);'],
                    ['type' => 'Button', 'caption' => '⏹ Stop simulation', 'onClick' => 'echo ANWSIM_SetActive($id, false);'],
                    ['type' => 'Button', 'caption' => '📅 Show today\'s schedule', 'onClick' => 'echo ANWSIM_Preview($id);'],
                ]],
                ['type' => 'Label', 'caption' => 'The switch "Simulation active" below the instance does the same — also from WebFront/tile, by event or script.'],
                ['type' => 'ExpansionPanel', 'expanded' => false, 'caption' => '▶️  Start and end', 'items' => [
                    ['type' => 'SelectVariable', 'name' => 'TriggerID', 'caption' => 'Trigger variable (optional)'],
                    ['type' => 'ValidationTextBox', 'name' => 'TriggerValues', 'caption' => 'Values that mean "simulate" (number/text variables, comma-separated, e.g. 3,4)'],
                    ['type' => 'CheckBox', 'name' => 'TriggerInvert', 'caption' => 'Invert trigger (simulate while the condition is NOT met, e.g. a variable "Present")'],
                    ['type' => 'NumberSpinner', 'name' => 'TriggerDelay', 'caption' => 'Start delay', 'suffix' => ' min', 'minimum' => 0, 'maximum' => 720],
                    ['type' => 'Label', 'name' => 'TriggerStatus', 'caption' => $this->triggerStatusText()],
                    ['type' => 'Label', 'caption' => 'ℹ️ Boolean variable: TRUE starts, FALSE stops. Number or text variable (e.g. a house mode): the simulation runs while the value is one of the listed values. The start can be delayed so that a short errand does not start it; stopping always takes effect immediately. The switch "Simulation active" always stays operable by hand.'],
                    ['type' => 'Select', 'name' => 'EndAction', 'caption' => 'When the simulation ends', 'options' => [
                        ['caption' => 'Leave everything as it is', 'value' => self::END_NOTHING],
                        ['caption' => 'Restore the state from before the start', 'value' => self::END_RESTORE],
                        ['caption' => 'Switch off all replayed channels', 'value' => self::END_OFF],
                    ]],
                ]],
                ['type' => 'ExpansionPanel', 'expanded' => false, 'caption' => '🧠  Learning and reference day', 'items' => [
                    ['type' => 'NumberSpinner', 'name' => 'LearnDays', 'caption' => 'Learning window', 'suffix' => ' days', 'minimum' => 7, 'maximum' => 365],
                    ['type' => 'CheckBox', 'name' => 'CoherentReplay', 'caption' => 'All channels replay the same reference day (the order between rooms stays real)'],
                    ['type' => 'NumberSpinner', 'name' => 'MinDayEvents', 'caption' => 'Minimum switching events per reference day', 'minimum' => 0, 'maximum' => 500],
                    ['type' => 'Label', 'caption' => 'ℹ️ A learned day only serves as reference if at least this many switching events were recorded on it (all channels together) — so a day on which nobody was home is not replayed. 0 = no minimum.'],
                    ['type' => 'Select', 'name' => 'HolidayRegion', 'caption' => 'Public holidays (replayed like Sundays)', 'options' => $regions],
                    ['type' => 'Label', 'caption' => $astro],
                ]],
                ['type' => 'ExpansionPanel', 'expanded' => false, 'caption' => '🔔  Notifications', 'items' => [
                    ['type' => 'CheckBox', 'name' => 'NotifyFailure', 'caption' => 'Notify when a switching fails (at most once per channel and day)'],
                    ['type' => 'CheckBox', 'name' => 'NotifyStartStop', 'caption' => 'Notify when the simulation starts or stops'],
                    [
                        'type' => 'List', 'name' => 'NotifyTargets', 'caption' => 'Send to these visualizations (empty = all)', 'rowCount' => 3, 'add' => true, 'delete' => true,
                        'columns' => [
                            ['caption' => 'Visualization', 'name' => 'Instance', 'width' => 'auto', 'add' => 0, 'edit' => ['type' => 'SelectInstance']],
                        ],
                    ],
                    ['type' => 'Label', 'name' => 'NotifyStatus', 'caption' => $this->notifyStatusText()],
                    ['type' => 'Button', 'caption' => '🔔 Send test notification', 'onClick' => 'echo ANWSIM_TestNotification($id);'],
                    ['type' => 'Label', 'caption' => 'ℹ️ While nobody is home, nobody notices that a lamp no longer responds — the notification tells you. Notifications are sent as push to the tile visualization or the WebFront; apply changes before testing. Failed switchings are always written to the message log as well.'],
                ]],
            ],
        ];
    }

    /** Kanal-Zusammenfassung (auch für UpdateFormField nach einer Aktion). */
    private function channelSummaryText(): string
    {
        $summary = [];
        $first = [];
        foreach ($this->channels() as $ch) {
            $tid = (int)($ch['TargetID'] ?? 0);
            $name = $this->channelName($ch);
            if ($tid <= 0 || !IPS_VariableExists($tid)) {
                $summary[] = $this->t('⚠️ %s: target variable missing or no longer exists.', $name);
                continue;
            }
            if (isset($first[$tid])) {
                $summary[] = $this->t('⚠️ %1$s: same target variable as "%2$s" — this row is ignored. Remove one of the two.', $name, $first[$tid]);
                continue;
            }
            $first[$tid] = $name;
            $word = (int)($ch['Mode'] ?? self::MODE_OFF) === self::MODE_LEARN_PLAY ? $this->blockedWord($ch) : null;
            if ($word !== null) {
                $summary[] = $this->t('⛔ %1$s: is never switched — "%2$s" is on the list of devices that are not switched.', $name, $word);
                continue;
            }
            $buf = $this->buffer($tid);
            $n = count($buf);
            if ($n === 0) {
                $summary[] = $this->t('ℹ️ %s: nothing learned yet.', $name);
                continue;
            }
            $summary[] = $this->t('✅ %1$s: %2$s switching event(s) since %3$s, last %4$s.', $name, $n, date('d.m.Y', (int)$buf[0]['t']), date('d.m.Y H:i', (int)$buf[$n - 1]['t']))
                . ' ' . $this->t('Reference days: %s.', $this->coverageText($tid));
        }
        return $summary ? implode("\n", $summary) : 'ℹ️ No channels created yet.';
    }

    private function ChannelsPanel(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => true,
            'caption' => '💡  Channels',
            'items' => [
                ['type' => 'Label', 'caption' => 'One channel = one switchable target variable (e.g. living room light, floor lamp socket, kitchen shutter). Different channels of the same instance are replayed together believably, see above.'],
                ['type' => 'Button', 'caption' => '🔎 Find suitable variables', 'onClick' => 'echo ANWSIM_Discover($id);'],
                ['type' => 'Label', 'name' => 'DiscoverStatus', 'caption' => 'ℹ️ Not searched yet. The search lists logged, switchable variables that are switched regularly — nothing is created without your confirmation.'],
                [
                    'type' => 'List', 'name' => 'Suggestions', 'caption' => 'Suggestions', 'rowCount' => 8, 'visible' => false, 'values' => [],
                    // Keine Eigenschaft: nichts davon wird gespeichert (Muster wie im DG65-Toolkit Automat).
                    'add' => false, 'delete' => false, 'loadValuesFromConfiguration' => false,
                    'columns' => [
                        ['caption' => '☑︎', 'name' => 'Pick', 'width' => '45px', 'save' => false, 'edit' => ['type' => 'CheckBox']],
                        ['caption' => 'Name', 'name' => 'Caption', 'width' => '220px', 'save' => false],
                        ['caption' => 'Kind', 'name' => 'Kind', 'width' => '110px', 'save' => false],
                        ['caption' => 'Location', 'name' => 'Location', 'width' => 'auto', 'save' => false],
                        ['caption' => 'Days with switching', 'name' => 'Days', 'width' => '170px', 'save' => false],
                        ['caption' => 'Switchings per day', 'name' => 'PerDay', 'width' => '170px', 'save' => false],
                        ['caption' => 'ID', 'name' => 'TargetID', 'width' => '10px', 'visible' => false, 'save' => false],
                    ],
                ],
                ['type' => 'Button', 'name' => 'AddSuggestions', 'visible' => false, 'caption' => '➕ Add ticked as channels', 'onClick' => '$s = []; foreach ($Suggestions as $x) { $s[] = $x; } $c = []; foreach ($Channels as $x) { $c[] = $x; } echo ANWSIM_AddSuggestions($id, json_encode($s), json_encode($c));'],
                [
                    'type' => 'List', 'name' => 'Channels', 'caption' => 'Channels', 'rowCount' => 8, 'add' => true, 'delete' => true,
                    'columns' => [
                        ['caption' => 'Name', 'name' => 'Caption', 'width' => '180px', 'add' => '', 'edit' => ['type' => 'ValidationTextBox']],
                        ['caption' => 'Target variable', 'name' => 'TargetID', 'width' => 'auto', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                        ['caption' => 'Mode', 'name' => 'Mode', 'width' => '190px', 'add' => self::MODE_LEARN_PLAY, 'edit' => ['type' => 'Select', 'options' => [
                            ['caption' => 'Off', 'value' => self::MODE_OFF],
                            ['caption' => 'Learn + replay', 'value' => self::MODE_LEARN_PLAY],
                            ['caption' => 'Learn only (never switch)', 'value' => self::MODE_LEARN_ONLY],
                        ]]],
                        ['caption' => 'Time reference', 'name' => 'TimeRef', 'width' => '170px', 'add' => self::TIMEREF_CLOCK, 'edit' => ['type' => 'Select', 'options' => [
                            ['caption' => 'Clock time', 'value' => self::TIMEREF_CLOCK],
                            ['caption' => 'Sunset', 'value' => self::TIMEREF_SUNSET],
                            ['caption' => 'Sunrise', 'value' => self::TIMEREF_SUNRISE],
                        ]]],
                        ['caption' => 'Max. offset', 'name' => 'JitterMin', 'width' => '130px', 'add' => 10, 'edit' => ['type' => 'NumberSpinner', 'suffix' => ' min', 'minimum' => 0, 'maximum' => 180]],
                    ],
                ],
                ['type' => 'PopupButton', 'caption' => 'What do mode, time reference and offset mean?', 'width' => '420px', 'popup' => [
                    'caption' => 'Channel settings',
                    'items' => [
                        ['type' => 'Label', 'caption' => 'Mode — "Learn + replay": the normal case. "Learn only": observes only, never switches (for trying out). "Off": channel paused, learned data kept.'],
                        ['type' => 'Label', 'caption' => 'Time reference — "Clock time": the switching is replayed at the same time of day as on the reference day. "Sunset"/"Sunrise": at the same distance before/after the sun position — useful for lighting that follows dusk. Requires Symcon Location Control.'],
                        ['type' => 'Label', 'caption' => 'Max. offset — largest random time shift per day in minutes (± around the reference time). 0 = exactly as on the reference day; 10–20 look natural. With "shared reference day" all channels get the same relative offset, the order is kept.'],
                    ],
                ]],
                ['type' => 'Button', 'caption' => '📥 Fill learning memory from archive', 'onClick' => 'echo ANWSIM_ImportArchive($id);'],
                ['type' => 'Label', 'caption' => 'ℹ️ If a target variable is logged in the Symcon archive, the module takes over its switching history for the learning window — automatically when a channel is created, with this button for existing channels. Your own learned data is kept; only the time before it is filled.'],
                ['type' => 'ValidationTextBox', 'name' => 'BlockedWords', 'caption' => 'Never switch devices whose name contains one of these words (comma-separated)', 'width' => '100%'],
                ['type' => 'Label', 'caption' => 'ℹ️ Safety: heating appliances such as toasters or irons do not belong in a presence simulation, refrigerators must never go off, and door locks, garage doors or awnings must not move while nobody is home. Channels whose name, variable, device or profile contains one of these words are never switched and never suggested. Adjust the list to your installation.'],
                ['type' => 'Label', 'name' => 'ChannelSummary', 'caption' => $this->channelSummaryText()],
            ],
        ];
    }

    private function ForumHint(): ?array
    {
        if ($this->ReadAttributeBoolean('ForumHintGone')) {
            return null;
        }
        return [
            'type' => 'ExpansionPanel', 'name' => 'ForumHintPanel', 'expanded' => true,
            'caption' => '💬  Feedback',
            'items' => [
                ['type' => 'Label', 'caption' => '🧪 The presence simulation is new — questions, wishes or bug reports are welcome (Symcon forum or GitHub).'],
                ['type' => 'Button', 'caption' => 'Open repository', 'onClick' => "echo '" . self::REPO_URL . "';", 'link' => true],
                ['type' => 'Button', 'caption' => 'Got it – do not show again', 'onClick' => 'ANWSIM_AckForumHint($id);'],
            ],
        ];
    }

    /**
     * Lizenz-/Unterstützungs-Hinweis — Wortlaut verbundweit identisch
     * (SUITE.md "Einheitliche Formular-Optik", Variante A). Bewusst NICHT
     * dismissible, ganz unten nach dem Forum-Hinweis.
     */
    private function LicenseHint(): array
    {
        return [
            'type' => 'ExpansionPanel', 'expanded' => false,
            'caption' => '🧡  About this module',
            'items' => [
                ['type' => 'Label', 'caption' => 'Born out of genuine enthusiasm for my own installation — and a few evenings typed through. Still: hobby or not, this is intellectual property and real work went into it.'],
                ['type' => 'Label', 'caption' => 'Licence: PolyForm Noncommercial 1.0.0 — free for private and non-commercial use; commercial use requires a separate licence from the rights holder.'],
                ['type' => 'Button', 'caption' => 'View licence text', 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => 'Commercial use or questions about the licence? Just get in touch: dietmar@gureth.eu'],
                ['type' => 'Label', 'caption' => 'Like the module and want to leave something anyway? A small donation is appreciated — entirely voluntary, nothing expected in return.'],
                ['type' => 'Button', 'caption' => '☕  Donate via PayPal', 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true],
            ],
        ];
    }

    public function GetConfigurationForm()
    {
        $base   = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $status = $base['status'] ?? []; // Konsole übersetzt die Statustexte selbst (Store-Checkliste 25)

        $elements = array_values(array_filter(array_merge(
            [$this->PurposeIntro(), $this->NewsBanner(), $this->DocPanel()],
            [$this->SimulationPanel(), $this->ChannelsPanel()],
            [$this->ForumHint(), $this->LicenseHint()]
        )));

        return json_encode(['elements' => $elements, 'actions' => [], 'status' => $status]);
    }
}
