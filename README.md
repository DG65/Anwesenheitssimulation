🇬🇧 [English version](README.en.md)

# DG65-Toolkit Anwesenheitssimulation

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-0.3.3-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
![Check Style](https://github.com/DG65/Anwesenheitssimulation/actions/workflows/check-style.yml/badge.svg)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

Die **Anwesenheitssimulation** lässt ein leeres Haus bewohnt aussehen. Das Modul merkt sich, wann im Alltag wirklich
Licht, Rollläden, Steckdosen oder das Radio geschaltet werden — **jede schaltbare Symcon-Variable,
aus jedem Modul** — und spielt dieses echte Muster ab, sobald niemand da ist. Es erzeugt den
Lichtschein eines bewohnten Hauses und den Anschein, der damit einhergeht: der Schein trügt.
(Bis Version 0.1.3 hieß das Modul deshalb „Schein".)

Im Symcon-Store steht das Modul unter dem Namen **„DG65-Toolkit Bewohnersimulator"** (Store-Namen
dürfen höchstens 30 Zeichen haben). In Symcon selbst heißt die Instanz „Anwesenheitssimulation".

Symcon bietet im Store ein eigenes Modul „Anwesenheits-Simulation", das aus archivierten
(geloggten) Schaltvorgängen abspielt. Dieses Modul geht einen eigenen Weg: Es lernt selbst und
braucht kein Archiv (nutzt es aber, wenn vorhanden, um sofort loszulegen), verschiebt die
Schaltzeiten jeden Tag zufällig, kann Kanäle an Sonnenauf- und -untergang koppeln, startet auf
Wunsch über eine Abwesenheits-Variable oder einen Hausmodus, behandelt Feiertage wie Sonntage,
schaltet Wärmegeräte grundsätzlich nicht und zeigt vorab, was es heute abspielen würde.

## So funktioniert es

**Lernen.** Solange „Simulation aktiv" AUS ist, zeichnet das Modul jede echte Wertänderung der
Zielvariablen auf (per Kernel-Nachricht, kein Polling): Zeitpunkt und Wert, in einem versteckten
Lernspeicher je Kanal. Alles außerhalb des Lernfensters (Standard 28 Tage) fällt automatisch
heraus — das Muster folgt so der Jahreszeit von selbst. Schreibvorgänge ohne Wertänderung werden
ignoriert, ein Dimmer-Schieber (mehrere Werte binnen Sekunden) zählt als ein Schaltvorgang.

**Kürzere Lernphase mit dem Archiv.** Wird eine Zielvariable im Symcon-Archiv geloggt, übernimmt
das Modul ihren Schaltverlauf für das Lernfenster: bei neuen Kanälen automatisch, bei bestehenden
über „📥 Lernspeicher aus dem Archiv füllen". Selbst Gelerntes bleibt erhalten, gefüllt wird nur
die Zeit davor. Ohne Archiv-Logging lernt das Modul einfach selbst.

**Abspielen.** Ist die Simulation AN, wählt das Modul für jeden Tag einen echten **Vorbild-Tag**
desselben Wochentags aus dem Gelernten (ersatzweise Werktag/Wochenende) und spielt dessen
Schaltfolge ab — verschoben um einen zufälligen **Versatz** bis zum eingestellten Maximum. Beim
Start wird zuerst der Zustand hergestellt, den das Vorbild um diese Uhrzeit hatte („Einschwingen",
ohne den ganzen Tag nachzuspielen). Mit „gemeinsamer Vorbild-Tag" spielen alle Kanäle denselben
Tag mit demselben relativen Versatz: erst Wohnzimmer, dann Küche, dann alles aus — so wie an dem
echten Abend, an dem es gelernt wurde. Geschaltet wird sekundengenau wie am Vorbild-Tag. Ein Tag,
an dem kaum geschaltet wurde, dient nicht als Vorbild („Mindestzahl Schaltvorgänge je Vorbild-Tag",
Standard 4).

**Lange Abwesenheit.** Während die Simulation läuft, steht das Lernfenster still, und die Zeit,
in der sie lief, zählt auch danach nicht zum Fenster. Die gelernten Tage gehen also nicht aus,
egal wie lange niemand da ist.

**Zeitbezug.** Je Kanal wahlweise **Uhrzeit**, **Abstand zu Sonnenuntergang oder -aufgang**, **beides**
(morgens gilt der Aufgang, abends der Untergang) oder **Außenhelligkeit**. Beleuchtung folgt damit
der Dämmerung, nicht der Uhr. Für den Sonnenstand reicht Symcons eingebaute Location-Control-Instanz
(Einstellungen → Standort); ohne sie fällt der Kanal automatisch auf die Uhrzeit zurück — das
Formular sagt, was gerade gilt.

**Außenhelligkeit.** Mit einem Helligkeits- oder Einstrahlungssensor im Freien (z. B. von einer
Wetterstation, Lux oder W/m²) misst das Modul die Dämmerung, statt sie zu berechnen: Sie gilt, sobald
der Wert 10 Minuten lang unter der Abendschwelle bzw. über der Morgenschwelle liegt. Unter dunklen
Wolken geht das Licht dann früher an, an einem klaren Abend später — wie bei echten Bewohnern.
„🔎 Helligkeitssensor suchen" findet den Sensor, „📈 Schwellen vorschlagen" liest passende Schwellen
aus dem Archiv. Meldet der Sensor nichts, wird spätestens 90 Minuten nach Sonnenuntergang geschaltet.

**Feiertage.** Auf Wunsch zählen die gesetzlichen Feiertage einer Region (deutsche Bundesländer,
Österreich, Schweiz) wie Sonntage: An einem Feiertag wird ein Sonntag abgespielt, und ein gelernter
Feiertag dient selbst nur als Sonntags-Vorbild.

**Ende der Simulation.** In der Vorgabe bleiben die Zielvariablen so stehen, wie sie gerade sind.
Wahlweise stellt das Modul den Zustand von vor dem Start wieder her oder schaltet alles aus, was es
abgespielt hat.

**Sicherheit.** Geräte, deren Name ein Wort der Liste „nie schalten" enthält, schaltet das Modul
nie, egal was eingestellt ist. Vorbelegt sind Wärmegeräte wie Toaster, Bügeleisen, Heizlüfter oder
Wasserkocher, Kühl- und Gefriergeräte sowie Türschlösser, Garagentore und Markisen. Geprüft werden
die Bezeichnung des Kanals, der Name der Variable, der Name des Geräts und das Profil der Variable.
Die Liste lässt sich im Formular anpassen.

**Meldungen.** Auf Wunsch schickt das Modul eine Push-Nachricht an die Kachel-Visualisierung oder
das WebFront, wenn eine Schaltung fehlschlägt (höchstens einmal je Kanal und Tag) oder wenn die
Simulation startet oder endet. Die Ziele sind wählbar, in der Vorgabe ist beides aus.

Während die Simulation läuft, wird **nicht gelernt** — sonst würde das Modul seine eigenen Wiedergaben
als echtes Verhalten mitlernen.

## Einrichten

1. Instanz „Anwesenheitssimulation" anlegen (Suche nach „Anwesenheitssimulation").
2. Im Panel **„💡 Kanäle"** auf **„🔎 Passende Variablen suchen"** klicken: Das Modul listet die
   geloggten, schaltbaren Variablen, die regelmäßig geschaltet werden, mit ihrer Art (Licht,
   Dimmer, Rollladen, Sonstiges). Angehakt sind Lichter und Dimmer. Haken prüfen, „➕ Angehakte als Kanäle übernehmen", „Übernehmen" — der
   Schaltverlauf kommt dann sofort aus dem Archiv. Kanäle lassen sich auch von Hand anlegen:
   Bezeichnung, Zielvariable (Bool, Integer oder Float — z. B. ein Licht, eine Steckdose, ein
   Rollladen), Modus, Zeitbezug, Versatz.
3. Ein paar Tage normal leben lassen — das Panel zeigt je Kanal, wie viele Schaltvorgänge schon
   gelernt sind und für welche Wochentage es schon Vorbild-Tage gibt. **„📅 Heutigen Ablauf anzeigen"** zeigt jederzeit, was das Modul heute abspielen würde.
4. Simulation starten: Schalter **„Simulation aktiv"** unter der Instanz (auch aus WebFront/Kachel
   oder per Ereignis), die Formular-Buttons, oder automatisch über eine **Auslöser-Variable**:
   eine Bool-Variable wie „Abwesend" oder „Alarmanlage scharf" oder eine Zahl-/Text-Variable wie
   ein Hausmodus (dann die Werte nennen, bei denen simuliert werden soll, z. B. `3,4`). Für
   Variablen wie „Anwesend" gibt es „Auslöser umkehren". Eine **Startverzögerung** verhindert, dass
   ein kurzer Einkauf die Simulation startet; das Beenden wirkt immer sofort.

**Modus je Kanal:** „Lernen + Abspielen" (Normalfall), „Nur lernen" (beobachtet, schaltet nie —
zum Ausprobieren eines neuen Kanals), „Aus" (Kanal pausiert, Gelerntes bleibt).

**Mehrere Instanzen** sind erlaubt, z. B. je Etage oder Wohnung — jede lernt und spielt unabhängig.

## Skripte

```php
IPS_RequestAction(12345, 'Active', true);      // Simulation starten (false = beenden)
echo ANWSIM_SetActive(12345, true);            // dasselbe, mit Ergebnistext
echo ANWSIM_Preview(12345);                    // heutiger Ablauf als Text
echo ANWSIM_ForgetChannel(12345, 'Wohnzimmer'); // Gelerntes eines Kanals verwerfen
echo ANWSIM_ImportArchive(12345);               // Lernspeicher aus dem Archiv füllen
echo ANWSIM_Discover(12345);                    // passende Variablen suchen (füllt die Vorschlagsliste im Formular)
echo ANWSIM_TestNotification(12345);            // Probemeldung an die gewählten Visualisierungen
```

Instanzvariablen: `Active` (Simulation aktiv, Bool mit Aktion), `LastAction` (Letzte Schaltung),
`NextAction` (Nächste Schaltung), `Schedule` (Heutiger Ablauf: Tabelle für die Visualisierung mit
aktuellem und nächstem Stand je Kanal). Je Kanal eine versteckte Variable „Gelernt: …" (der Lernspeicher),
dazu versteckt „Simulationszeiten (intern)".
Fehlgeschlagene Schaltungen landen dauerhaft im Symcon-Meldungsprotokoll.

Das Modul setzt kein anderes Modul voraus und wird von keinem vorausgesetzt. Es bietet keinen
Daten-Vertrag für andere Module — die Instanzfunktionen oben sind reine Bedien-Einstiege.

## Fragen und Rückmeldungen

Das Modul hat einen eigenen Thread im Symcon-Forum:
[DG65-Toolkit Bewohnersimulator - Anwesenheitssimulation, die aus dem echten Alltag lernt](https://community.symcon.de/t/144973).
Fragen, Wünsche und Fehlerberichte sind dort willkommen.

## Sprache

Alle sichtbaren Texte gibt es auf Deutsch und Englisch; das Modul folgt der Sprache der
Symcon-Installation. Deutsch ist die Referenz, die englischen Texte sind mit derselben Sorgfalt
gepflegt.

## Prüfen ohne Symcon

```
php .tools/test-sim.php           # vier Lernwochen + ein Simulationstag, Auslöser, Sonnenuntergang, Resync, Formular, beide Sprachen
php .tools/check-standalone.php   # kein ungesicherter Fremdaufruf (function_exists-Wächter)
```

Beides läuft auch in der GitHub-Actions-CI (`check-style.yml`).

## Lizenz

PolyForm Noncommercial 1.0.0, siehe [LICENSE](LICENSE) — privat und nicht-kommerziell frei
nutzbar, für den gewerblichen Einsatz ist eine gesonderte Lizenz vom Rechteinhaber nötig
(Kontakt: dietmar@gureth.eu). Spenden willkommen: [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth).

---

Teil des **DG65-Toolkit** — eigenständige, domänenfreie Symcon-Bausteine von DG65, ohne
Energiebezug: Raumstruktur, Formularschutz, Messwertrechner, Gerätetausch, Automat,
Batteriewächter und die Anwesenheitssimulation. Alle Repositories unter
[github.com/DG65](https://github.com/DG65).
