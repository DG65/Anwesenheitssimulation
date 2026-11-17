# Store-Texte (Bundle eu.dg.anwesenheitssimulation)

Stand 17.11.2026 — Beta eingereicht und sofort veröffentlicht: Release 21508, Commit cb8f0c6
(0.3.0 Build 8), Kategorien Sicherheit (2) und Komfort (3), `close_lower_channels` = 0.
Stable: noch keine (erste Stable nur auf Dietmars Wunsch).

**Store-Name weicht vom Modulnamen ab.** Der Store erlaubt höchstens 30 Zeichen;
„DG65-Toolkit Anwesenheitssimulation" hat 35. Dietmar hat am 17.11.2026 „DG65-Toolkit
Bewohnersimulator" gewählt (30 Zeichen). Im Modul, im Formular und auf GitHub bleibt es bei
„Anwesenheitssimulation"; die Beschreibung nennt deshalb im ersten Absatz beide Namen. Die
Store-Suche durchsucht Name, Beschreibung und `library.json` — „Anwesenheitssimulation" findet
das Modul (geprüft am 17.11.2026).

## de — Name
DG65-Toolkit Bewohnersimulator

## en — Name
DG65-Toolkit Resident Sim.

## de — Beschreibung
Der Bewohnersimulator ist eine Anwesenheitssimulation aus dem DG65-Toolkit: Er lässt ein leeres Haus bewohnt aussehen. Er merkt sich, wann im Alltag wirklich Licht, Rollläden, Steckdosen oder das Radio geschaltet werden, und spielt dieses echte Muster ab, sobald niemand da ist. Das geht mit jeder schaltbaren Variable aus jedem Modul. In Symcon heißt die Instanz „DG65-Toolkit Anwesenheitssimulation“.

Lernen: Jede echte Wertänderung der gewählten Variablen wird mit Zeitpunkt gemerkt. Ein Archiv ist dafür nicht nötig. Wird eine Variable aber im Archiv geloggt, übernimmt das Modul ihren bisherigen Schaltverlauf und ist sofort einsatzbereit. Das Lernfenster (Standard 28 Tage) rollt mit, so folgt das Muster der Jahreszeit.
Abspielen: Für jeden Tag wählt das Modul einen echten Vorbild-Tag desselben Wochentags und spielt dessen Schaltfolge sekundengenau ab, verschoben um einen zufälligen Versatz. Auch bei langer Abwesenheit gehen die gelernten Tage nicht aus. Alle Kanäle nehmen auf Wunsch denselben Vorbild-Tag, die Reihenfolge zwischen den Räumen bleibt echt.
Zeitbezug: Je Kanal Uhrzeit oder Abstand zu Sonnenauf- oder -untergang (über die Location Control).
Einrichten: „Passende Variablen suchen“ schlägt die geloggten Lichter, Dimmer, Rollläden und Steckdosen vor, die regelmäßig geschaltet werden. Anhaken, übernehmen, fertig.
Start: Schalter „Simulation aktiv“ (auch in der Visualisierung), Knopf im Formular, Skript oder eine Auslöser-Variable. Das kann eine Abwesenheits-Variable sein oder ein Hausmodus, auf Wunsch mit Startverzögerung.
Ende: Alles so lassen, den Zustand von vor dem Start wiederherstellen oder alles Abgespielte ausschalten.
Feiertage: Auf Wunsch werden Feiertage wie Sonntage abgespielt (deutsche Bundesländer, Österreich, Schweiz).
Sicherheit: Wärmegeräte wie Toaster oder Bügeleisen, Kühlgeräte, Türschlösser, Garagentore und Markisen werden nie geschaltet. Die Wortliste dafür ist anpassbar.
Meldungen: Auf Wunsch kommt eine Push-Nachricht, wenn eine Schaltung fehlschlägt oder die Simulation startet oder endet.
Übersicht: „Heutigen Ablauf anzeigen“ zeigt vorab, was heute abgespielt würde. Die Variable „Heutiger Ablauf“ zeigt in der Visualisierung den aktuellen und den nächsten Stand je Kanal. „Letzte Schaltung“ und „Nächste Schaltung“ stehen ebenfalls als Variablen bereit.

Während die Simulation läuft, wird nicht gelernt. Das Modul folgt der Sprache deines Symcon (Deutsch oder Englisch).

## en — Description
The Resident Simulator is a presence simulation from the DG65-Toolkit: it makes an empty house look lived-in. It remembers when lights, shutters, sockets or the radio are really switched in everyday life and replays that real pattern as soon as nobody is home. This works with any switchable variable from any module. In Symcon the instance is called "DG65-Toolkit Anwesenheitssimulation".

Learning: every real value change of the chosen variables is remembered with its time. No archive is needed. But if a variable is logged in the archive, the module takes over its switching history and is ready to use right away. The learning window (default 28 days) rolls along, so the pattern follows the season.
Replaying: for each day the module picks a real reference day of the same weekday and replays its switching sequence to the second, shifted by a random offset. Even during long absences the learned days do not run out. If desired, all channels use the same reference day, so the order between the rooms stays real.
Time reference: per channel clock time or distance to sunrise or sunset (via Location Control).
Setup: "Find suitable variables" suggests the logged lights, dimmers, shutters and sockets that are switched regularly. Tick, add, done.
Start: the switch "Simulation active" (also in the visualization), a button in the form, a script or a trigger variable. That can be an absence variable or a house mode, with a start delay if desired.
End: leave everything as it is, restore the state from before the start, or switch off everything that was replayed.
Public holidays: if desired, public holidays are replayed like Sundays (German federal states, Austria, Switzerland).
Safety: heating appliances such as toasters or irons, refrigerators, door locks, garage doors and awnings are never switched. The word list for this can be adjusted.
Notifications: if desired, you get a push notification when a switching fails or the simulation starts or stops.
Overview: "Show today's schedule" shows in advance what would be replayed today. The variable "Today's schedule" shows the current and the next state per channel in the visualization. "Last switching" and "Next switching" are available as variables, too.

While the simulation is running, nothing is learned. The module follows the language of your Symcon (English or German).

## de — Versionsinformation (0.3.2)
- Fix: Die Beschriftung der Werteliste beim Auslöser wird nicht mehr abgeschnitten.
- Fix: „Heutiger Ablauf“ sagt jetzt, wenn ein Kanal heute gar nicht geschaltet wird.

## en — Release notes (0.3.2)
- Fix: the caption of the trigger value list is no longer cut off.
- Fix: "Today's schedule" now says when a channel is not switched at all today.

## de — Versionsinformation (0.3.1)
- Neu: Das Panel „Rückmeldungen“ und die Dokumentation verlinken den Modul-Thread im Symcon-Forum.

## en — Release notes (0.3.1)
- New: the feedback panel and the documentation link to the module thread in the Symcon forum.

## de — Versionsinformation (0.3.0)
- Neu: Erste Veröffentlichung im Store.
- Neu: Zweisprachig (Deutsch/Englisch).
- Neu: Lernen ohne Archiv, Abspielen nach dem Vorbild-Tag-Prinzip mit Zufallsversatz.
- Neu: Schaltverlauf geloggter Variablen wird aus dem Archiv übernommen, damit entfällt die Lernphase.
- Neu: Zeitbezug je Kanal (Uhrzeit, Sonnenuntergang, Sonnenaufgang).
- Neu: Start per Schalter, Formular, Skript oder Auslöser-Variable (auch Hausmodus, mit Startverzögerung); Vorschau des heutigen Ablaufs.
- Neu: Vorschläge für passende Variablen aus dem Archiv.
- Neu: Wählbar, was beim Ende der Simulation geschieht.
- Neu: Variable „Heutiger Ablauf“ für die Visualisierung.
- Neu: Feiertage lassen sich wie Sonntage abspielen.
- Neu: Wärmegeräte wie Toaster oder Bügeleisen, Türschlösser und Markisen werden nie geschaltet.
- Neu: Push-Nachricht bei fehlgeschlagener Schaltung, auf Wunsch auch bei Start und Ende.
- Neu: Die Kanalübersicht zeigt die Vorbild-Tage je Wochentag und warnt bei doppelter Zielvariable.

## en — Release notes (0.3.0)
- New: first release in the store.
- New: bilingual (German/English).
- New: learning without an archive, replay by the reference-day principle with random offset.
- New: the switching history of logged variables is taken over from the archive, so no learning phase is needed.
- New: time reference per channel (clock time, sunset, sunrise).
- New: start via switch, form, script or trigger variable (also a house mode, with start delay); preview of today's schedule.
- New: suggestions for suitable variables from the archive.
- New: choose what happens when the simulation ends.
- New: variable "Today's schedule" for the visualization.
- New: public holidays can be replayed like Sundays.
- New: heating appliances such as toasters or irons, door locks and awnings are never switched.
- New: push notification when a switching fails, optionally also on start and stop.
- New: the channel overview shows the reference days per weekday and warns about a target variable used twice.

## Dokumentation
Verbund-Regel: der Doku-Link zeigt in beiden Sprachen auf die deutsche README.
de: https://github.com/DG65/Anwesenheitssimulation/blob/beta/README.md
en: https://github.com/DG65/Anwesenheitssimulation/blob/beta/README.md

## Hinweise zur Einreichung
- Im Store gibt es bereits Symcons eigenes Modul „Anwesenheits-Simulation“ (de.symcon.presencesimulation,
  Version 1.4, Stable und Beta, ab Symcon 6.0, Quelle github.com/symcon/AnwesenheitsSimulation). Es spielt
  archivierte Schaltvorgänge eines der letzten vier gleichen Wochentage sekundengenau ab. Unsere Texte
  vergleichen nicht wertend, sondern nennen nur die eigenen Merkmale. Vergleich am Quellcode: 17.11.2026.
- Die drei Rückstände aus dem Vergleich sind in 0.3.0 behoben (Leerlaufen bei langer Abwesenheit,
  Minutentakt, keine Mindestzahl je Vorbild-Tag); dazu kamen der Archiv-Import und sechs
  Ergänzungen (Vorschläge, Auslöser mit Hausmodus und Verzögerung, Endaktion, Übersicht für die
  Visualisierung, Feiertage, Sperrliste für Wärme- und Kühlgeräte).
- Erste Einreichung nur Kanal Beta (erledigt 17.11.2026); Stable erst auf Dietmars Wunsch (Release-Regel).
- Vor jeder Einreichung `publish_set_release_flags(closeLowerChannels=false)` setzen.
- `library.json`: `date` auf die Unixzeit des Release setzen, `build` = Zahl der Commits an `library.json`.
