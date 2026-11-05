🇩🇪 [Deutsche Fassung](README.md)

# DG65-Toolkit Presence Simulation

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Module Version](https://img.shields.io/badge/Module_Version-0.3.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
![Check Style](https://github.com/DG65/Anwesenheitssimulation/actions/workflows/check-style.yml/badge.svg)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

The **presence simulation** makes an empty house look lived-in. The module remembers when lights,
shutters, sockets or the radio are really switched in everyday life — **any switchable Symcon
variable, from any module** — and replays that real pattern as soon as nobody is home. It creates
the glow of an occupied house and the impression that goes with it. (Up to version 0.1.3 the module
was therefore called "Schein", German for both "glow" and "appearance".)

Symcon offers its own module "Anwesenheits-Simulation" in the store, which replays archived
(logged) switching events. This module takes its own approach: it learns by itself and needs no
archive (but uses one, if present, to get going right away), shifts the switching times randomly
every day, can tie channels to sunrise and sunset, starts via an absence variable or a house mode
if desired, treats public holidays like Sundays, never switches heating appliances and shows in
advance what it would replay today.

## How it works

**Learning.** As long as "Simulation active" is OFF, the module records every real value change of
the target variables (via kernel message, no polling): time and value, in a hidden learning memory
per channel. Everything outside the learning window (default 28 days) drops out automatically, so
the pattern follows the season by itself. Writes without a value change are ignored; a dimmer
slider (several values within seconds) counts as one switching event.

**Shorter learning phase with the archive.** If a target variable is logged in the Symcon archive,
the module takes over its switching history for the learning window: automatically for new
channels, via "📥 Fill learning memory from archive" for existing ones. Your own learned data is
kept; only the time before it is filled. Without archive logging the module simply learns by itself.

**Replaying.** When the simulation is ON, the module picks for each day a real **reference day** of
the same weekday from the learned data (otherwise weekday/weekend) and replays its switching
sequence — shifted by a random **offset** up to the configured maximum. At start it first restores
the state the reference day had at this time ("settling", without replaying the whole day). With
"shared reference day" all channels replay the same day with the same relative offset: first the
living room, then the kitchen, then everything off — just like on the real evening it was learned.
Switching happens to the second, as on the reference day. A day on which hardly anything was
switched does not serve as reference ("Minimum switching events per reference day", default 4).

**Long absences.** While the simulation is running the learning window stands still, and the time
it ran does not count towards the window afterwards. So the learned days do not run out, however
long nobody is home.

**Time reference.** Per channel either **clock time** or **distance to sunset/sunrise**. Lighting
then follows dusk, not the clock. The built-in Symcon Location Control instance (Settings →
Location) is all that is needed; without it the channel automatically falls back to clock time —
the form tells you which applies.

**Public holidays.** If desired, the public holidays of a region (German federal states, Austria,
Switzerland) count like Sundays: on a holiday a Sunday is replayed, and a learned holiday itself
only serves as a Sunday reference.

**End of the simulation.** By default the target variables stay as they are. Optionally the module
restores the state from before the start, or switches off everything it replayed.

**Safety.** Devices whose name contains a word from the "never switch" list are never switched,
whatever is configured. Preset are heating appliances such as toasters, irons, fan heaters or
kettles, refrigerators and freezers, plus door locks, garage doors and awnings. The channel name,
the variable name, the device name and the variable's profile are checked. The list can be adjusted
in the form.

**Notifications.** If desired, the module sends a push notification to the tile visualization or
the WebFront when a switching fails (at most once per channel and day) or when the simulation
starts or stops. The targets can be chosen; by default both are off.

While the simulation is running, **nothing is learned** — otherwise the module would learn its own
replays as real behaviour.

## Setup

1. Create an instance "Presence Simulation" (search for "Anwesenheitssimulation" or "Presence").
2. In the panel **"💡 Channels"** click **"🔎 Find suitable variables"**: the module lists the
   logged, switchable variables that are switched regularly, with their kind (light, dimmer,
   shutter, other). Ticked are lights and dimmers. Check the ticks, "➕ Add ticked as channels", "Apply" — the switching history then
   comes from the archive right away. Channels can also be added by hand: name, target variable
   (boolean, integer or float — e.g. a light, a socket, a shutter), mode, time reference, offset.
3. Live normally for a few days — the panel shows per channel how many switching events have been
   learned and for which weekdays there are reference days already. **"📅 Show today's schedule"** shows at any time what the module would replay today.
4. Start the simulation: the switch **"Simulation active"** below the instance (also from
   WebFront/tile or by event), the form buttons, or automatically via a **trigger variable**:
   a boolean variable such as "Away" or "Alarm armed", or a number/text variable such as a house
   mode (then list the values at which to simulate, e.g. `3,4`). For variables like "Present"
   there is "Invert trigger". A **start delay** keeps a short errand from starting the simulation;
   stopping always takes effect immediately.

**Mode per channel:** "Learn + replay" (the normal case), "Learn only" (observes, never switches —
for trying out a new channel), "Off" (channel paused, learned data kept).

**Several instances** are allowed, e.g. per floor or flat — each learns and replays independently.

## Scripts

```php
IPS_RequestAction(12345, 'Active', true);        // start the simulation (false = stop)
echo ANWSIM_SetActive(12345, true);              // the same, with a result text
echo ANWSIM_Preview(12345);                      // today's schedule as text
echo ANWSIM_ForgetChannel(12345, 'Living room'); // discard the learned data of one channel
echo ANWSIM_ImportArchive(12345);                // fill the learning memory from the archive
echo ANWSIM_Discover(12345);                     // find suitable variables (fills the suggestion list in the form)
echo ANWSIM_TestNotification(12345);             // test notification to the chosen visualizations
```

Instance variables: `Active` (simulation active, boolean with action), `LastAction` (last switching),
`NextAction` (next switching), `Schedule` (today's schedule: a table for the visualization with
current and next state per channel). Per channel a hidden variable "Learned: …" (the learning memory),
plus the hidden "Simulation periods (internal)".
Failed switchings are logged permanently in the Symcon message log.

The module does not require any other module and is not required by any. It offers no data contract
for other modules — the instance functions above are plain operating entry points.

## Language

All visible texts are available in German and English; the module follows the language of your
Symcon installation. German is the reference; the English texts are maintained with the same care.

## Testing without Symcon

```
php .tools/test-sim.php           # four learning weeks + one simulation day, trigger, sunset, resync, form, both languages
php .tools/check-standalone.php   # no unguarded call into other modules (function_exists guard)
```

Both also run in the GitHub Actions CI (`check-style.yml`).

## Licence

PolyForm Noncommercial 1.0.0, see [LICENSE](LICENSE) — free for private and non-commercial use;
commercial use requires a separate licence from the rights holder (contact: dietmar@gureth.eu).
Donations welcome: [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth).

---

Part of the **DG65-Toolkit** — independent, domain-free Symcon building blocks by DG65, without
energy focus: Raumstruktur (room structure), Formularschutz (form protection), Messwertrechner
(measurement calculator), Gerätetausch (device replacement), Automat, Batteriewächter (battery
monitor) and the presence simulation. All repositories at [github.com/DG65](https://github.com/DG65).
