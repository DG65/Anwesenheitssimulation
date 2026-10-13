# DG65-Toolkit Anwesenheitssimulation

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-0.2.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
![Check Style](https://github.com/DG65/Anwesenheitssimulation/actions/workflows/check-style.yml/badge.svg)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

Die **Anwesenheitssimulation** lässt ein leeres Haus bewohnt aussehen. Das Modul merkt sich, wann im Alltag wirklich
Licht, Rollläden, Steckdosen oder das Radio geschaltet werden — **jede schaltbare Symcon-Variable,
aus jedem Modul** — und spielt dieses echte Muster ab, sobald niemand da ist. Es erzeugt den
Lichtschein eines bewohnten Hauses und den Anschein, der damit einhergeht: der Schein trügt.
(Bis Version 0.1.3 hieß das Modul deshalb „Schein".)

Anders als Symcons eingebaute Anwesenheitssimulation ist dieses Modul nicht an IPSLight gebunden, braucht
kein Symcon-Archiv und arbeitet nicht mit starren Zeitplänen, die jeder Beobachter nach zwei
Abenden durchschaut.

## So funktioniert es

**Lernen.** Solange „Simulation aktiv" AUS ist, zeichnet das Modul jede echte Wertänderung der
Zielvariablen auf (per Kernel-Nachricht, kein Polling): Zeitpunkt und Wert, in einem versteckten
Lernspeicher je Kanal. Alles außerhalb des Lernfensters (Standard 28 Tage) fällt automatisch
heraus — das Muster folgt so der Jahreszeit von selbst. Schreibvorgänge ohne Wertänderung werden
ignoriert, ein Dimmer-Schieber (mehrere Werte binnen Sekunden) zählt als ein Schaltvorgang.

**Abspielen.** Ist die Simulation AN, wählt das Modul für jeden Tag einen echten **Vorbild-Tag**
desselben Wochentags aus dem Gelernten (ersatzweise Werktag/Wochenende) und spielt dessen
Schaltfolge ab — verschoben um einen zufälligen **Versatz** bis zum eingestellten Maximum. Beim
Start wird zuerst der Zustand hergestellt, den das Vorbild um diese Uhrzeit hatte („Einschwingen",
ohne den ganzen Tag nachzuspielen). Mit „gemeinsamer Vorbild-Tag" spielen alle Kanäle denselben
Tag mit demselben relativen Versatz: erst Wohnzimmer, dann Küche, dann alles aus — so wie an dem
echten Abend, an dem es gelernt wurde.

**Zeitbezug.** Je Kanal wahlweise **Uhrzeit** oder **Abstand zu Sonnenuntergang/-aufgang**.
Beleuchtung folgt damit der Dämmerung, nicht der Uhr. Dafür reicht Symcons eingebaute
Location-Control-Instanz (Einstellungen → Standort); ohne sie fällt der Kanal automatisch auf die
Uhrzeit zurück — das Formular sagt, was gerade gilt.

Während die Simulation läuft, wird **nicht gelernt** — sonst würde das Modul seine eigenen Wiedergaben
als echtes Verhalten mitlernen. Beim Beenden bleiben die Zielvariablen so stehen, wie sie gerade
sind.

## Einrichten

1. Instanz „Anwesenheitssimulation" anlegen (Suche nach „Anwesenheitssimulation").
2. Im Panel **„💡 Kanäle"** je Zielvariable eine Zeile anlegen: Bezeichnung, Zielvariable (Bool,
   Integer oder Float — z. B. ein Licht, eine Steckdose, ein Rollladen), Modus, Zeitbezug, Versatz.
   „Übernehmen".
3. Ein paar Tage normal leben lassen — das Panel zeigt je Kanal, wie viele Schaltvorgänge schon
   gelernt sind. **„📅 Heutigen Ablauf anzeigen"** zeigt jederzeit, was das Modul heute abspielen würde.
4. Simulation starten: Schalter **„Simulation aktiv"** unter der Instanz (auch aus WebFront/Kachel
   oder per Ereignis), die Formular-Buttons, oder automatisch über eine **Auslöser-Variable**
   (Bool, z. B. „Abwesend", „Urlaub", „Alarmanlage scharf"; für Variablen wie „Anwesend" die
   Option „invertiert").

**Modus je Kanal:** „Lernen + Abspielen" (Normalfall), „Nur lernen" (beobachtet, schaltet nie —
zum Ausprobieren eines neuen Kanals), „Aus" (Kanal pausiert, Gelerntes bleibt).

**Mehrere Instanzen** sind erlaubt, z. B. je Etage oder Wohnung — jede lernt und spielt unabhängig.

## Skripte

```php
IPS_RequestAction(12345, 'Active', true);      // Simulation starten (false = beenden)
echo ANWSIM_SetActive(12345, true);            // dasselbe, mit Ergebnistext
echo ANWSIM_Preview(12345);                    // heutiger Ablauf als Text
echo ANWSIM_ForgetChannel(12345, 'Wohnzimmer'); // Gelerntes eines Kanals verwerfen
```

Instanzvariablen: `Active` (Simulation aktiv, Bool mit Aktion), `LastAction` (Letzte Schaltung),
`NextAction` (Nächste Schaltung). Je Kanal eine versteckte Variable „Gelernt: …" (der Lernspeicher).
Fehlgeschlagene Schaltungen landen dauerhaft im Symcon-Meldungsprotokoll.

Das Modul setzt kein anderes Modul voraus und wird von keinem vorausgesetzt. Es bietet keinen
Daten-Vertrag für andere Module — die drei Instanzfunktionen oben sind reine Bedien-Einstiege.

## Prüfen ohne Symcon

```
php .tools/test-sim.php           # vier Lernwochen + ein Simulationstag, Auslöser, Sonnenuntergang, Resync, Formular
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
