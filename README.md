# News

Kurze interne Nachrichten an Benutzer und Benutzergruppen, mit Lesestatus.

## Funktionen

- Linkes Menü unter **Start → NEWS**: News erstellen, Gesendete, Erhaltene, Alle News (nur mit Recht)
- Empfänger: einzelne Benutzer, Benutzergruppen, optional „alle aktiven Benutzer“ (eigenes Recht)
- Gruppen werden **beim Senden** in eine feste Empfängerliste aufgelöst. Wer später in die Gruppe kommt, erhält alte News nicht.
- Absender, deaktivierte und externe Benutzer werden übersprungen, Mehrfachtreffer zählen nur einmal.
- Öffnen = gelesen
- Banner „Du hast X neue News“ auf jeder Seite (rot bei wichtigen News), Zähler am Menüeintrag
- Optional gelbes Popup über ein Agenda-Ereignis (Typ `AC_NEWS`) mit Browser-Erinnerung, abschaltbar in der Einrichtung
- Klick auf das Popup öffnet direkt die News (Weiterleitung von der Agenda-Karte)
- Wichtig-Markierung
- Zurückziehen (Absender oder Recht „Lesestatus aller News“), Löschen (eigenes Recht)
- Entitätsübergreifend: News gelten für Personen, nicht für eine Firma

## Rechte

| Id | Recht | Standard |
|---|---|---|
| 501261 | Eigene News lesen | an |
| 501262 | News erstellen und senden | aus |
| 501263 | An alle Benutzer senden | aus |
| 501264 | Lesestatus aller News sehen, alle zurückziehen | aus |
| 501265 | News löschen | aus |

## Bekannte Einschränkung

Das gelbe Popup wird von `core/ajax/check_notifications.php` nur für die Entität angezeigt, in der die
Erinnerung angelegt wurde. Das Modul legt sie in der Haupt-Entität des Empfängers an (`llx_user.entity`,
Superadmins: 1). Arbeitet ein Empfänger in einer anderen Entität, sieht er das Banner, aber kein Popup.

## Voraussetzungen

- Dolibarr 22+
- Für das Popup: Modul Agenda, Browser-Erinnerungen (`AGENDA_REMINDER_BROWSER`)

## Lizenz

GPL-3.0-or-later – Kim Wittkowski <kim@wittkowski-it.de>
