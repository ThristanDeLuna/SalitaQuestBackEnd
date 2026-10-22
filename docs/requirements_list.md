# Salita Quest: Requirements list (Checking 1)

Source: Product Requirements (PRD), FR1 to FR17. Region codes: `tagalog`, `cebuano`, `hiligaynon` (shown as "Cebuano (Bisaya)").
Roles: Visitor (signed out), Learner, Contributor, Admin. Owners: A platform and login, B map and content, C audio and games, D submissions and notices.

| Feature | Requirement (PRD ID) | Who uses it | Owner |
|---|---|---|---|
| Sign in | Google sign-in; the API verifies the token and issues its own JWT (FR1) | Everyone who signs in | A |
| Access control | Every protected endpoint checks the JWT and the role; region and word reads are public (FR2) | System | A |
| Map | One pin per region, usable without signing in (FR3) | Visitor and up | B |
| Word list | Word, translation, example sentence and audio player per region, usable without signing in (FR4) | Visitor and up | B |
| Content admin | Admin creates, edits and deletes regions and words (FR5) | Admin | B |
| Audio upload | Admin uploads a pronunciation file; stored on Cloudinary, URL saved (FR6) | Admin | C |
| Audio playback | Audio plays in the word list and the listening quiz (FR7) | Visitor and up | C |
| Matching game | 4 pairs, scored on the server, 10 points per pair, sign-in required (FR8) | Learner and up | C |
| Listening quiz | 5 audio questions with 4 options, scored on the server, sign-in required (FR9) | Learner and up | C |
| Stamps | A region stamp when both games are passed at 60% or more (FR10) | Learner and up | C |
| Passport | Points, stamps, game status and streak, sign-in required (FR11) | Learner and up | C |
| Streak | Calculated from dates when the user plays or opens the passport (FR12) | Learner and up | C |
| Word of the Day | Chosen from the date; first feature to drop if time runs short (FR13) | Visitor and up | C |
| Submit a word | A Contributor submits a word; the form shows a labeled MyMemory suggestion (FR14) | Contributor, Admin | D |
| Approve or reject | Admin approves or rejects; only approved words reach learners (FR15) | Admin | D |
| Decision email | The contributor gets an email through Brevo when a submission is decided (FR16) | Contributor | D |
| API logging | Every external request and response made by the API is recorded in `api_logs` (FR17) | System | A, all |

**Integrations (5):** Google OAuth, Leaflet + OpenStreetMap (browser only), Cloudinary, Brevo, MyMemory. The four called by the API are logged in `api_logs`.

**Out of scope:** flashcards, a third game, welcome and streak emails, in-browser audio recording, region polygons, more than 3 regions.

**Non-functional:** Google sign-in and JWT on protected endpoints; secrets only in environment variables; works on phone and laptop; friendly errors when an outside service fails; free tiers only; LavaLust MVC with one library per integration.
