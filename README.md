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

---

## English

**News** is a Dolibarr module for short internal messages to users and user groups, with read tracking.

- Left menu under **Home → NEWS**: create, sent, received, all news (with permission)
- Recipients: individual users, user groups, optionally all active users (separate permission).
  Groups are resolved into a fixed recipient list when the news is sent.
- Opening a news item marks it as read
- Banner "You have X new news" on every page (red for important news) and an unread counter in the menu
- Optional yellow popup via an agenda event (type `AC_NEWS`) with browser reminder, can be switched off in setup
- Withdraw (author or "read status of all news" permission) and delete (own permission)
- Works across entities (MultiCompany): news are addressed to people, not to a company

**Requirements:** Dolibarr 22+. For the popup: Agenda module and browser reminders (`AGENDA_REMINDER_BROWSER`).

**Installation:** unzip into `htdocs/custom/news` (the folder must be named `news`), then activate the module under Setup → Modules.

**Languages:** German, English.

**License:** GPL-3.0-or-later
