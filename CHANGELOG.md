# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [0.3.3] - 2026-11-18

### Added
- **Zeitbezug „Sonnenauf- und -untergang"** (Wunsch von Roberto im Forum, Beitrag 2): je Schaltung
  gilt der Sonnenstand, der am Vorbild-Tag näher lag — morgens der Aufgang, abends der Untergang.
- **Zeitbezug „Außenhelligkeit"** (Wunsch von Roberto; Dietmar: Wetterstation mit
  Einstrahlungssensor suchen und einbinden): Ein Helligkeits- oder Einstrahlungssensor misst die
  Dämmerung. Sie gilt, wenn der Wert im Fenster um den Sonnenstand 10 Minuten unter der
  Abendschwelle bzw. über der Morgenschwelle bleibt. Gemessen wird immer (auch beim Lernen), je Tag
  in der versteckten Variable `Twilight`; für Vorbild-Tage ohne Messung einmalig aus dem Archiv des
  Sensors. Heute gilt vor der Bestätigung die übliche Zeit (Sonnenstand plus mittlerer Abstand der
  letzten Tage); ist die vorbei und es ist noch hell, wartet die Wiedergabe, längstens bis 90 min
  nach Sonnenuntergang (morgens 120 min nach Aufgang). Fehlt Sensor oder Messung, gilt der
  Sonnenstand. `ANWSIM_FindBrightness()` sucht den Sensor (Wetterstation vor Innenraum-Melder,
  keine LED-Helligkeit), `ANWSIM_SuggestBrightness()` schlägt die Schwellen aus dem Archiv vor.
- **Kanalliste wächst mit** (8 bis 25 Zeilen; Roberto: „Fenster zu wenig hoch"), Vorschlagsliste
  12 Zeilen.

### Changed
- **Fortschritt der Wiedergabe als Zähler** (`done` je Kanal) statt als Uhrzeit: Weil sich die
  Zeiten bei „Außenhelligkeit" im Lauf des Tages verschieben, wird eine vorgerückte Schaltung
  nachgeholt und eine nach hinten gerückte nicht doppelt abgespielt. Laufende Simulationen aus
  älteren Versionen werden beim ersten Tick umgestellt.
- **Sonnenstand unabhängig von der Uhrzeit zugeordnet:** Bisher galt der Sonnenuntergang, der
  „jetzt" am nächsten lag — nachts also der gestrige. Jetzt behält jede Schaltung den Tagesabstand
  ihres Vorbilds (eine Schaltung um 01:00 hängt am Vorabend, eine um 20:00 am selben Abend) und hat
  damit den ganzen Tag über dieselbe Zeit. Vorschau und „Heutiger Ablauf" zeigen nachts nicht mehr
  die Abend-Schaltungen zuerst.
- **Formular nach der 40-Zeichen-Regel** (SUITE.md, 17.11.2026): kurze Beschriftungen, die
  Erklärung als `<Feld>Hint` direkt dahinter; der Prüfstand misst das fertige Formular in Deutsch
  und Englisch. Die Abhilfe aus 0.3.2 (volle Breite) entfällt.

## [0.3.2] - 2026-11-18

### Fixed
- Formular, Untergruppe „Start und Ende": Die Beschriftung des Felds „Werte, die ‚simulieren'
  bedeuten …" wurde abgeschnitten (Dietmars Bildschirmfoto vom 18.11.2026). Das Feld nutzt jetzt
  die volle Breite.
- Variable „Heutiger Ablauf": Ein Kanal, der an seinem Vorbild-Tag gar nicht geschaltet wurde,
  zeigte nur Striche. Jetzt steht dort „heute keine Schaltung".

## [0.3.1] - 2026-11-18

### Added
- **Forum-Thread verlinkt:** Das Panel „💬 Rückmeldungen" und die Dokumentation führen zum
  Modul-Thread im Symcon-Forum (https://community.symcon.de/t/144973, gepostet 18.11.2026).
  Der Hinweis führt jetzt einen Stand (Attribut `ForumHintSeen` = `thread-144973`): Wer ihn früher
  weggeklickt hat, als es noch keinen Thread gab, sieht ihn mit dem Link einmal wieder (Muster
  Batteriewächter 0.11.4). Das alte Attribut `ForumHintGone` bleibt registriert.
- README (de/en): Abschnitt „Fragen und Rückmeldungen" mit dem Thread, dazu der Store-Name
  „DG65-Toolkit Bewohnersimulator" (der Store erlaubt höchstens 30 Zeichen).

## [0.3.0] - 2026-11-17

### Added
- **Vorschläge aus dem Archiv** (17.11.2026): „🔎 Passende Variablen suchen" bzw.
  `ANWSIM_Discover()` listet geloggte Bool-Variablen mit Aktion, die im Lernfenster an mindestens
  drei Tagen geschaltet wurden (höchstens 150 Schaltungen je Tag, sonst ist es ein Takt). Vor-angehakt
  ist nur, was nach Beleuchtung aussieht; Wallbox, Fahrzeug & Co. stehen ungehakt darunter.
  „➕ Angehakte als Kanäle übernehmen" (`ANWSIM_AddSuggestions()`) hängt sie an die Kanalliste im
  offenen Formular — gespeichert wird erst mit „Übernehmen" (keine Selbstpersistenz), danach kommt
  der Schaltverlauf sofort aus dem Archiv. Bezeichnung = Gerätename, bei Doppelungen mit
  Variablennamen. Live nachgestellt: 81 Kandidaten, 7000 Archivwerte, 97 ms.
  Gefunden werden auch **Dimmer und Rollläden** (Zahl-Variablen mit Aktion, erkannt am Profil
  `~Intensity…`/`~Shutter…` oder am Namen; andere schaltbare Zahlen wie Sollwerte oder Ladeströme
  nicht). Spalte „Art"; vor-angehakt sind Lichter und Dimmer, Rollläden nicht. Eine Fahrt mit
  Zwischenwerten zählt als ein Schaltvorgang.
- **Meldung aufs Handy** (17.11.2026): optional Push an Kachel-Visualisierung/WebFront, wenn eine
  Schaltung fehlschlägt (`NotifyFailure`, höchstens einmal je Kanal und Tag) oder die Simulation
  startet/endet (`NotifyStartStop`); Ziele wählbar (`NotifyTargets`, leer = alle), Probemeldung
  per Knopf bzw. `ANWSIM_TestNotification()`. Vorgabe: aus. Technik nach SUITE.md
  (`VISU_PostNotificationEx`, Titel/Text byte-sicher gekürzt).
- **Kanalübersicht:** je Kanal die gelernten Vorbild-Tage je Wochentag („Mo 4 · Di 4 · … · So –")
  und eine Warnung, wenn dieselbe Zielvariable in zwei Zeilen steht (die zweite wird ignoriert,
  damit nicht doppelt geschaltet wird).
- **Auslöser erweitert:** neben Bool- jetzt auch Zahl- und Text-Variablen (z. B. ein Hausmodus) mit
  Werteliste `TriggerValues`; **Startverzögerung** `TriggerDelay` in Minuten (zweiter Zeitgeber,
  `ANWSIM_TriggerDelayElapsed()`), damit ein kurzer Einkauf nichts startet. Beenden wirkt sofort.
  Das Formular zeigt, was der Auslöser gerade sagt (formatierter Wert, Rohwert, Wirkung).
- **Ende der Simulation wählbar** (`EndAction`): alles lassen (Vorgabe, wie bisher), Zustand von vor
  dem Start wiederherstellen oder alle abgespielten Kanäle ausschalten. Geschaltet wird nur, was
  abweicht und was die Simulation heute tatsächlich abgespielt hat; ist der Startzustand unbekannt
  (Modul-Resync während der Simulation), wird nichts geschaltet.
- **Variable „Heutiger Ablauf"** (`Schedule`, `~HTMLBox`) für die Visualisierung: je Kanal
  Vorbild-Tag, aktueller Stand mit Uhrzeit, nächster Stand mit Uhrzeit. Kanalnamen HTML-sicher.
- **Feiertage wie Sonntage** (Dietmar, 17.11.2026): Eigenschaft `HolidayRegion` (16 Bundesländer,
  Österreich, Schweiz; Vorgabe: nicht berücksichtigen). Gilt für „heute" UND für gelernte Tage.
  Rechenregeln wie im DG65-Toolkit Automat, bewusst kopiert (jedes Toolkit-Modul läuft allein).
- **Geräte, die nie geschaltet werden** (Dietmar, 17.11.2026: „die gefährlichen Dinge wie Toaster,
  Bügeleisen etc. schalten einfach lassen"): Wortliste `BlockedWords`, vorbelegt mit Wärmegeräten
  Kühl- und Gefriergeräten sowie Türschlössern, Garagentoren und Markisen, vom Nutzer änderbar.
  Passt ein Wort auf Kanalbezeichnung, Variablenname, Gerätename oder Profil, wird der Kanal nie abgespielt und nie vorgeschlagen; Vorschau und
  Kanal-Zusammenfassung sagen es mit dem Wort (⛔). Lange Wörter passen auch als Wortteil, kurze
  (unter 7 Buchstaben) nur am Wortanfang, damit „Heater" kein „Theater" sperrt. Gelernt wird weiter.
  Am Live-System gegengeprüft: Toaster, Bügeleisen und Kaffeemaschine sind dort schaltbare,
  geloggte Steckdosen und werden von der Liste erfasst; ebenso das Türschloss der Haustüre
  (Profil `~DoorLockMode.ZWave`), die Markise und „Verriegelt" am Fahrzeug.
- **Zweisprachig Deutsch/Englisch** (Dietmar, 05.11.2026, SUITE.md Konventionen Punkt 9): alle
  sichtbaren Texte — Formular, Rückmeldungen, Variablennamen, Statuszeilen, Protokollmeldung,
  „Neu"-Panel — als englische Quelltexte im Code, Deutsch in `locale.json` (wortgleich zum
  bisherigen Stand). Dazu `README.en.md`. Das Modul folgt der Sprache der Symcon-Installation.
- Übersetzung nach dem Automat-Muster (SUITE.md Stolperstein 24): die Sprache wird einmal pro
  PHP-Lauf ermittelt und die Tabelle selbst gelesen — kein `Translate()`-Aufruf je Text im
  minütlichen Tick.
- Kennungen unverändert (Idents, Properties, Attribute, `ANWSIM_`-Funktionen, Listenfelder);
  bestehende Instanzen verlieren nichts. Bereits angelegte Variablen behalten ihre deutschen
  Namen (Symcon benennt nur bei Neuanlage).
- Prüfstand: läuft weiter auf Deutsch (alle bisherigen Prüfungen unverändert), neuer Block 15
  erzeugt Formular, Vorschau und Rückmeldungen auf Englisch und prüft sie auf deutsche Reste;
  statisch: jeder `t()`-Text steht in `locale.json`, Platzhalter stimmen überein, keine Umlaute
  in Code-Zeichenketten.

- **Archiv-Import (Dietmars Idee, 17.11.2026):** Wird eine Zielvariable im Symcon-Archiv geloggt,
  übernimmt das Modul ihren Schaltverlauf für das Lernfenster — bei neuen Kanälen automatisch,
  bei bestehenden über „📥 Lernspeicher aus dem Archiv füllen" bzw. `ANWSIM_ImportArchive()`.
  Gefüllt wird nur die Zeit VOR den selbst gelernten Daten (nichts wird ersetzt oder verdoppelt);
  Zeiten, in denen die Simulation lief, werden übersprungen; die Sonnen-Anker werden aus dem
  Standort der Location Control berechnet (`date_sun_info`, live sekundengleich mit deren Werten).
  Damit ist das Modul bei geloggten Variablen am ersten Tag nutzbar.
- **Mindestzahl Schaltvorgänge je Vorbild-Tag** (neue Einstellung `MinDayEvents`, Standard 4, alle
  Kanäle zusammen): ein Tag, an dem kaum geschaltet wurde, dient nicht als Vorbild. Erreicht kein
  Tag die Mindestzahl, gelten die vorhandenen Tage, mit Hinweis in Vorschau und Start-Meldung.

### Fixed
- **Erste echte Schaltung nach einer Simulation:** Der Lernspeicher kennt nur den Stand von vor der
  Abwesenheit. Hatte die Wiedergabe (oder die neue Endaktion) einen Kanal anders hinterlassen, ging
  die erste echte Schaltung nach der Rückkehr als „keine Änderung" verloren. Jetzt merkt sich das
  Modul den Stand jedes Kanals am Ende der Simulation (Attribut `EndState`) und vergleicht die
  erste Meldung danach damit. Dadurch wird auch die späte Rückmeldung einer Endaktion nicht als
  echtes Verhalten gelernt.
- **Lange Abwesenheit:** Bisher lief das Modul leer, sobald die Abwesenheit länger dauerte als das
  Lernfenster (nachgestellt: ab Abwesenheitstag 29 keine Schaltung mehr), weil während der
  Simulation nicht gelernt wird und das Fenster weiterrollte. Jetzt zählt die Zeit, in der die
  Simulation lief, nicht zum Fenster: es steht während der Simulation still, und nach der Rückkehr
  sind die Tage vor der Abreise noch da. Die Simulationszeiten liegen in der versteckten Variable
  `SimPeriods` (überlebt einen Modul-Resync, anders als ein Attribut).
- **Sekundengenau statt Minutentakt:** Der Timer wird auf die nächste Schaltzeit gestellt
  (spätestens nach 10 Minuten bzw. zum Tageswechsel). An/Aus-Folgen innerhalb derselben Minute
  hoben sich bisher unsichtbar auf; jetzt werden sie so abgespielt, wie sie gelernt wurden. Sind
  nach einem Ausfall mehrere Schaltungen eines Kanals fällig, wird nur die letzte ausgeführt.
- Nach einem Modul-Resync während laufender Simulation zeigt das Formular wieder das richtige
  „seit …" (aus `SimPeriods` wiederhergestellt).

### Changed
- Debug-Ausgaben (`SendDebug`) sind Entwickler-Kanal und jetzt durchgehend englisch.
- Store-Checkliste 25 (Review-Auswertung 06.11.2026): statische Formulartexte werden NICHT vom
  Modul übersetzt — die Konsole übersetzt Captions, Labels, Suffixe und Optionen selbst über
  `locale.json`. Selbst übersetzt werden nur Texte mit eingesetzten Werten (Statuszeile,
  Kanal-Zusammenfassung, Versionszeile, „Neu bis Version X"), Variablennamen und Rückmeldungen.
  Der Prüfstand bildet die Konsolen-Übersetzung nach und prüft, dass jeder statische Caption in
  `locale.json` steht.
- Store-Checkliste 26: verwaiste Lernspeicher werden mit `$this->UnregisterVariable()` statt
  `IPS_DeleteVariable()` entfernt.
- Store-Vorprüfung (17.11.2026): die Lernspeicher-Variablen werden nur noch bei der Neuanlage
  versteckt, nicht mehr bei jedem Übernehmen — wer sie einblendet, behält das. README und
  CHANGELOG stellen Symcons eigenes Modul „Anwesenheits-Simulation" jetzt richtig dar (arbeitet
  mit archivierten Schaltvorgängen; die frühere Aussage „an IPSLight gekoppelt" war falsch).
- Gegen Checkliste 27–29 geprüft, nicht betroffen: kein Instanz-Abgleich in `ApplyChanges()`
  (Location Control nur in Formular/Lernen/Abspielen nach `KR_READY`), nur Standardprofile
  `~Switch` und `~HTMLBox` per `MaintainVariable()`, kein Logging-Schalter.
- Formular: Das Panel „Simulation" hat zwei Untergruppen („Start und Ende", „Lernen und
  Vorbild-Tag"). Es bleibt bei zwei Fachpanels auf oberster Ebene.
- Die Beschriftung der Umkehr-Option heißt jetzt „Auslöser umkehren" (Eigenschaft `TriggerInvert`
  unverändert).

## [0.2.2] - 2026-10-23

### Fixed
- Das Lernfenster griff nur beim Lernen (Trimmen beim Schreiben). Ein Kanal, der länger nicht
  geschaltet wurde, behielt veraltete Tage und bot sie als Vorbild-Tag an; die Kanal-
  Zusammenfassung zählte sie mit (live: „Wohnzimmer: 5 Schaltvorgänge seit 13.09." bei einem
  Fenster ab 25.09.). Jetzt wird das Fenster auch beim Lesen angewendet — nur lesend, der
  Rohspeicher bleibt bis zum nächsten Lernvorgang unverändert. Prüfstand Block 14.
- Dabei präzisiert: Die Fenstergrenze ist der **Tagesanfang** vor N Tagen, nicht „jetzt minus
  N Tage". Ein Vorbild-Tag liegt damit ganz im Fenster oder ganz draußen — bei minütlich
  rollender Grenze wären die späten Ereignisse des ältesten Tags mitten im Abspielen
  herausgefallen (im Prüfstand sofort sichtbar: Wohnzimmer-„Aus" um 23:35 fehlte). Das
  Einschwingen (`stateBefore()`) liest bewusst den Rohspeicher, weil es den Zustand VOR dem
  Vorbild-Tag braucht, der am Fensterrand bereits außerhalb liegt.

## [0.2.1] - 2026-10-18

### Changed
- Lernspeicher (`buf_*`) werden nur noch gelöscht, wenn der Kanal aus der Liste entfernt oder
  seine Zielvariable gewechselt wurde — nicht mehr, wenn die Zielvariable bloß vorübergehend
  fehlt (z. B. weil ein Geräte-Modul beim Start/Update seine Variablen neu anlegt). „Im Zweifel
  nie löschen", SUITE.md Store-Review-Checkliste Punkt 17 (EMS-Rundruf 18.10.2026). Der
  `KR_READY`-Wächter vor jeder Löschung war schon da; der Prüfstand deckt beides jetzt ab
  (Block 13: Start vor KR_READY, fehlende/zurückkehrende Zielvariable, echte Entfernung).
- Geprüft, nicht betroffen: keine Presentation-OPTIONS im Modul (SUITE.md Stolperstein 9,
  Farbschlüssel je Darstellung) — nur klassische Profile; Prüfstand wacht darüber.

## [0.2.0] - 2026-10-13

### Changed
- **Umbenennung** (Dietmar, 13.10.2026, DG65-Toolkit-Welle „Funktionsnamen statt Behördenbild",
  TOOLKIT.md): „Schein" → **„DG65-Toolkit Anwesenheitssimulation"**. Vollständig, weil das Modul
  bisher nur bei Dietmar läuft: PHP-Klasse und Modulordner `DG65Anwesenheitssimulation` (mit
  Vorsatz, weil Symcons eigenes Modul `AnwesenheitsSimulation` heißt und PHP-Klassennamen
  case-insensitiv sind), Präfix `ANWSIM_` statt `SCHEIN_` (`ANWSIM_SetActive`, `ANWSIM_Preview`,
  `ANWSIM_ForgetChannel`), Repo `DG65/Schein` → `DG65/Anwesenheitssimulation` (GitHub-
  Weiterleitung bleibt, alter Name wird nie wiederverwendet), genau ein Alias.
- **GUIDs bleiben** — bestehende Instanzen hängen sich beim Neuregistrieren über dieselbe GUID
  wieder an, Kanäle (Property) und Lernspeicher (`buf_*`-Variablen) bleiben erhalten. **Verloren
  gehen beim Modulverwaltungs-Neuregistrieren nur Attribute:** der Tagesplan einer gerade
  laufenden Simulation (wird beim nächsten Tick neu gewählt) und die weggeklickten Hinweise
  (Zweck-Intro/Neu/Rückmeldungen erscheinen einmal wieder).
- Skripte/Ereignisse mit `SCHEIN_…` müssen auf `ANWSIM_…` umgestellt werden (bei Dietmar vor dem
  Release geprüft: keine vorhanden).

## [0.1.3] - 2026-10-12

### Changed
- Protokollierung fehlgeschlagener Schaltungen über `$this->LogMessage(…, KL_WARNING)` statt
  `IPS_LogMessage()` — die Schein-Instanz ist jetzt selbst Absender im Meldungsprotokoll
  (SUITE.md Store-Review-Checkliste Punkt 15, Symcon-Review 12.10.2026). Prüfstand wacht
  darüber, dass `IPS_LogMessage` in `module.php` nicht mehr vorkommt, und prüft den
  Fehlschlag-Pfad einer Schaltung.

## [0.1.2] - 2026-09-23

### Changed
- „🆕 Neu"-Panel nach dem verbundweiten `NEWS_VERSIONS`-Muster (SUITE.md Formular-Optik
  Punkt 1, Dashboard/Dietmar 23.09.2026): Einträge je Version, Banner zeigt nach einem Update
  nur die Lücke seit der zuletzt bestätigten Version („Neu bis Version X"), „Verstanden"
  speichert die tatsächlich installierte Bibliotheksversion. Bestehende Bestätigungen bleiben
  gültig (alter Wert „0.1.0" wird per `version_compare` weiter verstanden).

## [0.1.1] - 2026-09-13

### Changed
- Alle nutzersichtbaren Datumsangaben im Format TT.MM.JJJJ (Vorbild-Tag in „Letzte Schaltung",
  Vorschau, Start-Meldung, Statuszeile, Kanal-Zusammenfassung, Debug) — Verbund-Regel 9b aus
  SUITE.md (Dietmar, 13.09.2026). Intern bleibt der Vorbild-Tag maschinell (JJJJ-MM-TT im
  Tagesplan-Attribut), nur die Anzeige wandelt um. Prüfstand wacht jetzt über Datumsformat und
  echte Umlaute in Formular- und Statustexten.

## [0.1.0] - 2026-09-08

### Added
- Erstes Release. Anwesenheitssimulation für JEDE schaltbare Symcon-Variable (Bool/Integer/Float,
  z. B. Licht, Steckdose, Rollladen, Radio), unabhängig vom liefernden Modul und ohne
  Symcon-Archiv.
- **Lernen** ohne Symcon-Archiv: jede echte Wertänderung einer Zielvariable (per `VM_UPDATE`,
  kein Polling) landet mit Zeitstempel in einem versteckten Lernspeicher je Kanal; rollendes
  Lernfenster (Standard 28 Tage) — das Muster folgt so der Jahreszeit von selbst. Schreibvorgänge
  ohne Wertänderung werden ignoriert, Dimmer-Schieber (mehrere Werte binnen 20 s) zu einem
  Schaltvorgang zusammengefasst, Obergrenze 5000 Ereignisse je Kanal.
- **Abspielen nach dem Vorbild-Tag-Prinzip:** pro Tag wählt Schein einen echten gelernten Tag
  desselben Wochentags (ersatzweise Werktag/Wochenende, ersatzweise beliebig) und spielt dessen
  Schaltfolge mit zufälligem Versatz (je Kanal einstellbar, ± Minuten) ab. Beim Start und beim
  Tageswechsel wird zuerst der Zustand hergestellt, den das Vorbild um diese Uhrzeit hatte
  („Einschwingen", ohne Flackern). Option „gemeinsamer Vorbild-Tag": alle Kanäle spielen
  denselben Tag mit demselben relativen Versatz — die Reihenfolge zwischen den Räumen bleibt echt.
- **Zeitbezug je Kanal:** Uhrzeit oder Abstand zu Sonnenuntergang/-aufgang — nutzt Symcons
  eingebaute Location-Control-Instanz (Kernbestandteil), ohne sie automatischer Rückfall auf
  Uhrzeit mit Hinweis im Formular.
- **Start/Stopp:** Instanzvariable „Simulation aktiv" (mit Aktion — WebFront/Kachel, Ereignis,
  `IPS_RequestAction`), Formular-Buttons, `ANWSIM_SetActive()`, optionale Auslöser-Variable
  (z. B. Abwesend/Urlaub/Alarm scharf, invertierbar). Während der Simulation wird nicht gelernt.
- Sichtbare Rückmeldung: Variablen „Letzte Schaltung"/„Nächste Schaltung", Statuszeile und
  Kanal-Zusammenfassung im Formular, „📅 Heutigen Ablauf anzeigen" (`ANWSIM_Preview()`),
  `ANWSIM_ForgetChannel()`; fehlgeschlagene Schaltungen dauerhaft im Symcon-Log.
- Formular nach Verbund-Konvention (Zweck-Intro, News, Doku, Rückmeldungen, Über-dieses-Modul),
  Prüfstand `.tools/test-sim.php` (vier Lernwochen + Simulationstag ohne Symcon, läuft in der CI),
  `.tools/check-standalone.php`, PolyForm-Noncommercial-Lizenz.

### Herkunft
- Kandidat aus der DG65-Toolkit-Ideenrunde (Recherche der Destille-Sitzung, 08.09.2026:
  Forenberichte über Probleme mit vorhandenen Lösungen, ioBroker ohne Adapter,
  Home Assistant mit mehreren Blueprints als Beleg für den Wert des Konzepts). Kickoff-Briefing
  `Schein-Kickoff.md`.
