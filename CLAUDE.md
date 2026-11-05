# DG65-Toolkit Anwesenheitssimulation (vormals Schein) — Hinweise für die Arbeit an diesem Repository

## Einordnung

- **Marke: DG65-Toolkit**, nicht NRG-Stack. Domänenfrei: funktioniert für JEDE schaltbare Variable,
  unabhängig vom Gewerk. `library.json→name` = „DG65-Toolkit Anwesenheitssimulation", genau ein
  Alias (derselbe Text).
- **Umbenennung 13.10.2026 (Dietmar, Toolkit-Welle „Funktionsnamen", TOOLKIT.md-Abschnitt
  „Umbenennung auf Funktionsnamen"):** vormals „Schein" (Klasse `Schein`, Präfix `SCHEIN_`, Repo
  `DG65/Schein`). Jetzt Klasse + Modulordner **`DG65Anwesenheitssimulation`**, Präfix **`ANWSIM_`**,
  Repo **`DG65/Anwesenheitssimulation`**, lokaler Ordner `Anwesenheitssimulation/`. **GUIDs
  unverändert** (Bibliothek `{B4C508FF-…}`, Modul `{00F44496-…}`), Instanz #10795 bei Dietmar
  blieb erhalten. Der DG65-Vorsatz im Klassennamen ist Pflicht: Symcons eigenes Modul heißt
  `AnwesenheitsSimulation` (SymconMisc), PHP-Klassennamen sind case-insensitiv. Ab jetzt gilt die
  Einfrier-Faustregel wieder uneingeschränkt.
- **Kickoff:** `/Users/dietmar/Nextcloud/Claude/Schein-Kickoff.md` (Destille-Sitzung, 09.09.2026).
  Verbindliche Konventionen: `TOOLKIT.md` (Toolkit-spezifisch) und `SUITE.md` (Symcon-Allgemeines,
  Stolpersteine, Store-Review, Formular-Optik) — beide nur lokal unter `Nextcloud/Claude/`, nicht
  im Repo.
- **Kein Cross-Modul-Vertrag.** `ANWSIM_SetActive/Preview/ForgetChannel` sind Bedien-Einstiege für
  Skripte/Formular, kein Datenvertrag mit `contractVersion`. Sonnenauf-/-untergang kommt aus
  Symcons Kernmodul Location Control, nicht aus einem Toolkit-Partner (Katasteramt: „erst prüfen,
  ob der Kernel das schon löst, bevor ein Cross-Modul-Vertrag entsteht").

## Design-Entscheidungen (09.09.2026, Antworten auf die vier Kickoff-Fragen)

1. **Auslöser:** beides — Instanzvariable `Active` mit `EnableAction` (WebFront/Kachel/Ereignis/
   `IPS_RequestAction`) PLUS optionale Auslöser-Variable (`TriggerID`, `TriggerInvert`). Der
   manuelle Schalter bleibt immer bedienbar.
2. **Jahreszeit/Astro:** rollendes Lernfenster (28 Tage) folgt der Jahreszeit von selbst; zusätzlich
   je Kanal `TimeRef` Uhrzeit/Sonnenuntergang/Sonnenaufgang über Location Control
   (`{45E97A63-F870-408A-B259-2933F7EABF74}`, Idents `Sunset`/`Sunrise`, live verifiziert an
   IPS 9.0). Die Variablen zeigen immer aufs NÄCHSTE Ereignis → `anchorTs()` normalisiert auf
   ±12 h um den Bezugszeitpunkt. Jedes gelernte Ereignis trägt beide Anker (`ss`/`sr`), damit ein
   späterer Wechsel des Zeitbezugs mit alten Daten funktioniert.
3. **Ausreißer:** kein Filter, sondern das Vorbild-Tag-Prinzip — ein Party-Abend wird mit 1/N
   Wahrscheinlichkeit als Ganzes abgespielt, was realistischer ist als ein statistisches Mittel.
   Lernfenster instanzweit (nicht je Kanal wie im Kickoff-Entwurf): weniger Spalten, ein Wert.
4. **Gruppen:** 1 Kanal = 1 Zielvariable, aber `CoherentReplay` (Standard an): alle Kanäle spielen
   denselben Vorbild-Tag mit demselben relativen Versatz-Faktor — der Gruppeneffekt ohne
   Listen-Komplexität. Kanäle, die erst nach dem gemeinsamen Vorbild-Tag dazukamen, bekommen
   ein eigenes Vorbild statt fälschlich „war still".

## Kernmechanik — nicht verhandelbar

1. **Nie während der Simulation lernen** (`learn()` prüft `isActive()` zuerst). Sonst lernt das Modul
   seine eigenen Wiedergaben. Auch beim Ausbauen nie ein „lernt trotzdem mit"-Flag einführen.
2. **Nur `$Data[0]` von `VM_UPDATE` verwenden.** Die weiteren Indizes ([1]=geändert?) sind nur
   Community-Vermutung, nicht dokumentiert — `learn()` erkennt Wertänderungen selbst per Vergleich
   mit dem letzten Puffer-Eintrag.
3. **Lernspeicher = versteckte String-Variable `buf_<TargetID>`** unter der Instanz (kein Attribut,
   überlebt Modul-Resync; Schlüssel ist die Zielvariable, nicht die Bezeichnung — Umbenennen
   verliert nichts). Verwaiste Puffer räumt `ApplyChanges()` über den Ident-Präfix auf — **nur
   wenn der Kanal nicht mehr in der Liste steht**, nie weil die Zielvariable gerade fehlt (seit
   0.2.1, SUITE.md Store-Review Punkt 17: Fremdmodule legen Variablen beim Start/Update neu an).
   Vor `KR_READY` fasst `ApplyChanges()` nichts an, sondern wartet auf `IPS_KERNELMESSAGE`.
   `ReplayPlan` (Tagesplan) ist dagegen bewusst ein Attribut: geht es verloren, wählt der nächste
   Tick einfach neu.
4. **Einschwingen statt Nachspielen:** beim Start/Tageswechsel nur die letzte fällige Schaltung je
   Kanal (`settle()`), ersatzweise der Zustand vor Beginn des Vorbild-Tags (`stateBefore()`) — nie
   alle vergangenen Ereignisse des Tages in einer Sekunde durchschalten (Flackern).
5. **Koaleszieren nur vorwärts** (`$delta >= 0`): ein Zeitsprung rückwärts darf keine Ereignisse
   fressen (im Prüfstand mit absichtlich rückwärts laufender Uhr gefunden).
6. **Kalenderrechnung DST-sicher** (SUITE.md Stolperstein 18): `mktime()`/`strtotime('-N days')`,
   nie `±86400`.
6a. **Lernfenster: Grenze = Tagesanfang vor N Tagen, und es gilt auch beim LESEN** (0.2.2,
   Live-Fund 23.10.2026). `buffer()` liefert immer den getrimmten Stand (Vorbild-Wahl,
   Zusammenfassung, Abspielen), `rawBuffer()` nur für `stateBefore()` (Zustand vor dem
   Vorbild-Tag liegt am Fensterrand außerhalb). Nie die Grenze minütlich rollen lassen — sonst
   verliert der älteste Vorbild-Tag seine späten Ereignisse während des Abspielens.
7. **Schalten:** `HasAction()` → `RequestAction($vid, $wert)`, sonst `SetValue()`; Wert per
   `castForVariable()` auf den Variablentyp. Fehlschlag → `$this->LogMessage(…, KL_WARNING)`
   (dauerhaft), nicht nur `SendDebug()`. **Nie `IPS_LogMessage()` in der Modulklasse** (SUITE.md
   Store-Review Punkt 15, Symcon-Review 12.10.2026) — der Prüfstand hat keinen Stub dafür und
   prüft zusätzlich per `strpos`, dass es in `module.php` nicht vorkommt.
8. **Öffentliche Methoden:** nur skalare Parametertypen, keine PHP-Standardwerte (Stolperstein
   8/20), `SetActive()` (public) vs. `switchSimulation()` (private) — PHP-Methodennamen sind
   case-insensitiv, `setActive` wäre eine Kollision.
9. **Sprachregel:** alles Nutzersichtbare deutsch. **Keine eigene Anlage als Norm:** Beispiele
   („Wohnzimmerlicht", „Abwesend") nur als „z. B.", keine IDs, Defaults neutral (Trigger 0,
   Simulation aus).
10. **Datum sichtbar immer TT.MM.JJJJ** (SUITE.md Store-Checkliste 9b, Dietmar 13.09.2026) —
    `fmtDay()` für den Vorbild-Tag, `date('d.m.Y …')` für Zeitstempel, auch im `SendDebug()`.
    Der Vorbild-Tag bleibt intern `Y-m-d` (Schlüssel im Tagesplan-Attribut, `date()`-Vergleiche),
    nur die Anzeige wandelt. Der Prüfstand schlägt bei `JJJJ-MM-TT` in Formular/Status/Debug an.
11. **„Neu"-Panel = `NEWS_VERSIONS`-Array** (SUITE.md Formular-Optik Punkt 1, seit 0.1.2): bei
    jeder nutzersichtbaren Änderung einen Eintrag unter der neuen `library.json`-Version
    ergänzen — der Prüfstand verlangt, dass der höchste Schlüssel gleich der `library.json`-
    Version ist. `AckNews()` speichert die installierte Version (ohne Suffix), nie den Schlüssel.

12. **Zweisprachig (seit 0.3.0, SUITE.md Konventionen Punkt 9):** jeder sichtbare Text ist ein
    ENGLISCHER Quelltext, die deutsche Fassung steht in `locale.json`. **Statische Formulartexte
    (Captions, Labels, Suffixe, Optionen, form.json-Status) bleiben im Code roh englisch — die
    Konsole übersetzt sie selbst (Store-Checkliste 25, Reviewer: eigene Übersetzung „nicht
    erforderlich"). Nur Texte mit eingesetzten Werten, Variablennamen und Rückmeldungen
    (`SetActive`/`Preview`/`ForgetChannel`, `LastAction`/`NextAction`, Protokoll) gehen durch
    `$this->t('…', …args)`.** Die deutsche Fassung steht in
    `DG65Anwesenheitssimulation/locale.json` (Deutsch = Referenz, wortgleich zum früheren
    Stand). Kennungen (Idents, Properties, Attribute, `ANWSIM_`-Funktionen, Listenfelder) bleiben.
    `t()` ermittelt die Sprache einmal pro PHP-Lauf (Probe-Text „Simulation active") und liest
    die Tabelle selbst (Stolperstein 24, Muster `AUTOMATText::mitTabelle`) — nie `Translate()`
    je Text im Tick. Datum in beiden Sprachen TT.MM.JJJJ, „Uhr" nur im Deutschen (steckt in den
    Übersetzungen, nicht im Code). `SendDebug()` ist Entwickler-Kanal und bleibt englisch. Der
    Prüfstand verlangt: jeder `t()`-Text in `locale.json`, gleiche Platzhalter, keine Umlaute in
    Code-Zeichenketten, englisches Formular ohne deutsche Reste (Block 15). Neue Texte immer in
    BEIDEN Sprachen anlegen; `README.en.md` mitpflegen.

13. **Lernfenster und Simulationszeit (0.3.0):** `windowStart()` ist die EINZIGE Stelle, die das
    Fenster berechnet — Tagesanfang vor `LearnDays` Tagen, wobei Zeiten aus `SimPeriods` (versteckte
    Variable, JSON `[[von, bis|0], …]`, 0 = läuft) nicht mitzählen. Dadurch steht das Fenster während
    der Simulation still und läuft bei langer Abwesenheit nicht leer. `SimPeriods` bewusst als
    Variable (überlebt Resync); `switchSimulation()` öffnet/schließt den Eintrag.
14. **Wiedergabe sekundengenau (0.3.0):** `Tick()` stellt den Timer selbst auf die nächste Schaltzeit
    (höchstens `HEARTBEAT_S` = 600 s bzw. bis Mitternacht). Sind mehrere Schaltungen eines Kanals
    fällig (Ausfall), nur die letzte ausführen. Nie wieder einen festen Minutentakt einführen —
    An/Aus-Paare innerhalb einer Minute heben sich sonst auf.
15. **Mindestzahl je Vorbild-Tag (`MinDayEvents`, Standard 4, alle Kanäle zusammen):** filtert die
    Vorbild-Kandidaten in `buildPlan()`; erreicht kein Tag die Zahl, gelten alle Tage (`belowMin`
    → Hinweis). Nie „dunkles Haus" als Folge einer Einstellung.
16. **Archiv-Import (`importFromArchive()`, Dietmar 17.11.2026):** nur LESEND über Archive Control
    (`AC_GetLoggingStatus`/`AC_GetLoggedValues`, neueste zuerst, max. 10000, Werte typisiert — live
    geprüft). Füllt ausschließlich die Zeit VOR dem ältesten selbst gelernten Ereignis im Fenster;
    eigenes Gelerntes wird nie ersetzt. Simulationszeiten überspringen. Sonnen-Anker per
    `date_sun_info` mit den Koordinaten der Location Control (Eigenschaft `Location`). Automatisch
    nur bei Neuanlage eines Lernspeichers, sonst per Knopf. Lernen und Import teilen sich
    `appendEvent()` (gleiche Werte, Dimmer-Zusammenfassung).
17. **Sperrliste (`BlockedWords`, Dietmar 17.11.2026):** Geräte wie Toaster oder Bügeleisen werden
    NIE geschaltet — die Sperre sitzt in `playableChannels()`, der einzigen Quelle für alles, was
    schaltet (Wiedergabe, Einschwingen, Endaktion). Wer einen neuen Schaltweg baut, geht über
    `playableChannels()`. Bewusst KEINE „maximale Einschaltdauer": Dietmars Entscheidung war, solche
    Geräte gar nicht zu schalten. Die Vorgabeliste (`DEFAULT_BLOCKED`) ist Gerätenamen-Daten, deutsch
    und englisch in einer Zeile, nicht in `locale.json`.
18. **Feiertage (`HolidayRegion`):** `dayClass()` ist die einzige Stelle für „welcher Wochentag ist
    das" — Feiertag = 7 (Sonntag), für heute und für gelernte Tage. Nirgends sonst `date('N')` für
    die Vorbild-Wahl verwenden. Feiertagsregeln = Kopie aus Automat (`AUTOMATLogik::holiday`);
    Änderungen dort hier nachziehen.
19. **Auslöser:** weiterhin pegelgesteuert über `$Data[0]` (Regel 2), `triggerWants()` entscheidet
    (Bool, Werteliste, Umkehr). Startverzögerung über den Zeitgeber `StartDelay` und das Attribut
    `DelayUntil`; wird nur einmal angestoßen, Beenden und geänderte Einstellungen löschen sie.
20. **Endaktion (`EndAction`) und `EndState`:** `applyEndAction()` fasst nur Kanäle an, die im
    heutigen Plan stehen, und nur bei bekanntem Startzustand (`StartState`, Attribut). Danach hält
    `EndState` je Kanal fest, wie die Simulation ihn hinterlassen hat; `learn()` vergleicht die
    erste Meldung nach einer Simulation damit statt mit dem Puffer (`appendEvent(..., true)`).
21. **Vorschläge (`Discover()`/`AddSuggestions()`):** nur lesend im Archiv, legt nichts an. Liste
    nach Automat-Muster (keine Eigenschaft, alle Spalten `save => false`,
    `loadValuesFromConfiguration => false`); Übernahme per `UpdateFormField('Channels', 'values')`,
    nie `IPS_SetProperty` (Store-Regel). Vor-angehakt nur Beleuchtung (`LIGHT_WORDS`) — unter den
    geloggten Schaltvariablen sind auch Wallbox-Freigaben und Fahrzeug-Verriegelungen.
22. **Übersicht `Schedule`:** `updateScheduleView()` schreibt nur bei Änderung
    (`setStringIfChanged`), Aufruf in `Tick()`, beim Start/Stopp und in `ApplyChanges()`. Jeder
    Text darin läuft durch `htmlspecialchars`.
23. **Meldungen (`notify()`):** Push nach SUITE.md „Push-Benachrichtigung": beide Typen suchen
    (Kachel-Visualisierung `VISU_GUID`, WebFront `WEBFRONT_GUID`), `VISU_PostNotificationEx` mit
    6 Parametern, Titel 32 / Text 256 BYTES (`truncateBytes()`). Sparsam bleiben — Symcon hat ein
    Push-Limit (live gesehen 17.11.2026: „Request Limit für Push Nachrichten ist erreicht (250)",
    danach scheitert jeder Push bis zum Neustart). Deshalb Fehlschläge höchstens einmal je Kanal
    und Tag (`FailNotified`) und Vorgabe aus.
24. **Doppelte Zielvariable:** nur die erste Zeile zählt (`playableChannels()`, `channelByTarget()`);
    die Kanalübersicht warnt. Lernspeicher hängen an der Zielvariable, nicht an der Zeile.
25. **Vorschläge für Zahl-Variablen:** nur Dimmer und Rollläden (`suggestionKind()`, Profil oder
    Name). Schaltbare Zahlen sind sonst Sollwerte, Ladeströme, Betriebsarten, Türschloss-Modi —
    live nachgesehen, gut hundert solche Variablen auf Dietmars Anlage.

## Prüfen

```
php .tools/test-sim.php           # 0 = alle Blöcke bestanden (läuft auch in der CI)
php .tools/check-standalone.php   # 0 = kein ungesicherter Fremdaufruf
```

Der Prüfstand simuliert `IPSModule` + IPS-Kernfunktionen mit steuerbarer Uhr und Zufall
(`AnwsimTest` überschreibt `now()`/`randomUnit()`/`randomIndex()`). Live-Test in Symcon bleibt
Pflicht vor jedem Release: Installation nur manuell über die Modulverwaltung, Formular durchklicken,
jeden Button auf sichtbare Rückmeldung prüfen, mindestens einen Abend echt abspielen lassen.

## Branch-Modell

`main`+`beta` beim allerersten Commit gemeinsam, danach nur noch `beta` bis zum ersten echten
Live-Test (TOOLKIT.md, Präzedenzfall Pförtner). `LICENSE_URL` zeigt auf `main` — dieses Repo hat
von Anfang an PolyForm, keinen MIT-Altstand.

## Roadmap / bewusst nicht drin

- Vergleich mit Symcons eigenem Modul „Anwesenheits-Simulation" (Quellcode gelesen 17.11.2026): dort
  Archiv als einzige Quelle, sekundengenau, Mindestzahl je Tag, HTML-Vorschau in der Visualisierung;
  hier zusätzlich eigenes Lernen ohne Archiv, Zufallsversatz, Sonnenbezug, Auslöser-Variable,
  „Nur lernen", Vorschau vor dem Start, Hausmodus als Auslöser, Feiertage, Sperrliste, Vorschläge.
  Seit 0.3.0 gibt es auch hier eine HTML-Übersicht für die Visualisierung (`Schedule`).
- Bewusst nicht gebaut: zusätzliche Zufallselemente über den Versatz hinaus.
- Bekannte Eigenheit: Der Auslöser ist pegelgesteuert. Wer von Hand beendet, während der Auslöser
  noch „simulieren" sagt, bekommt beim nächsten Schreiben der Auslöser-Variable einen Neustart.
- Keine Wahrscheinlichkeits-/Slot-Statistik: Vorbild-Tag bewusst als Ganzes, nicht als Mittel.
- Keine WebFront-Listenpflege (SUITE.md „WebFront-taugliche Listenpflege") — Kanäle werden in der
  Konsole gepflegt, der Schalter „Simulation aktiv" reicht fürs WebFront.
- Kein Ferienbezug (Schulferien); Feiertage gibt es seit 0.3.0.
