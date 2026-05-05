# Audra Langues – Student Portal & AI Oral Practice

This project is a student portal developed for Audra Langues, with a built-in AI conversation space for English oral practice.

The goal is to provide students with a simple and coherent learning environment where they can:
- log in to their personal space,
- view their personal and course-related information,
- practise spoken English with an AI assistant,
- follow their learning progress.

## Project scope

The current scope of the project focuses on the **student side only**.

Included:
- student login page,
- student dashboard,
- personal information page,
- progress page,
- AI training page for oral conversation practice.

The AI conversation area is integrated directly into the **AI Training** page instead of appearing as a widget (initial idea).

## Main objective

The main objective of the project is to create a functional student portal with an AI-based oral practice tool that:
- encourages students to speak in English,
- keeps the conversation natural,
- lightly corrects grammar when needed,
- stays focused on a chosen topic,
- supports confidence and fluency.

## Current features

### Student portal
- login page,
- dashboard page,
- personal information page,
- progress page,
- AI training page,
- common logout system.

### AI conversation area
- topic selection before the conversation,
- text input,
- browser-based speech recognition,
- browser-based text-to-speech,
- backend connection through `chat.php`,
- conversation flow prepared for OpenAI Responses API.

## Technologies used

- PHP
- HTML
- CSS
- JavaScript
- PHP sessions
- Web Speech API
  - `SpeechRecognition`
  - `speechSynthesis`
- OpenAI Responses API (integration in progress / being refined)

## Project structure

- `portail_commun.php` / login page
- `creer_compte_eleve.php` / create account for the first time connection
- `mdp_oublie_eleve.php`/ password reset
- `send_password_reset_eleve.php`
- `reset_password_eleve.php`
- `process_reset_password_eleve.php`
- `dashboard_eleve.php`
- `entrainement_ia_eleve.php`
- `progres_eleve.php`
- `info_perso_eleve.php`
- `deconnexion_commune.php`
- `session_eleve.php`
- `assets/css/`
  - `portail_eleve.css`
  - `dashboard_eleve.css`
- `assets/js/`
  - `portail_eleve.js`
  - `creer_compte_eleve.js`
  - `widget_ai_eleve.js`
- `assets/api/chat.php`

## Security note

API keys must never be exposed in front-end code or committed to a public repository.
They should be stored securely on the server side. Recommandation; save it in the `.env`file :)

## Planning
| Week   | Task                                             | Objective                                                                                                                       |
| ------ | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| W1 | Validate chat functionality | Verify that the chatbot works correctly, that basic exchanges are possible, and that the conversation logic can be tested. |
| W2 | Create the student portal + Build the AI Training page | Build the student area, the login page, the dashboard, the main navigation, and the AI training structure + Create the main oral practice page where students can choose a topic and interact with the AI directly.|
| W3 | Finalise student pages and context handling + Integrate the database | Complete and organise the student pages, improve visual coherence, and allow the user to define or enrich their own context + Progressively connect the portal to real data, especially student, teacher, and course information.|
| W4 | Finalise progress page + responsive improvements |  Complete the page that shows the progress of the student based on the conversation that the student had with the AI which is also based on AI. |
| W5 | Test and improve voice mode| Test voice usage, improve the oral behaviour of the chatbot, adjust delays, and verify conversation fluency.|
| W6 |  |  |
| W7 | Fix issues and stabilise the application | Correct remaining errors, improve reliability, and polish the final technical and visual details. |
| Buffer | Unexpected issues | Keep extra time for technical issues, requested adjustments, or delays. |

## Current status

### Already completed:
- student portal base structure,
- login page,
- student dashboard,
- personal information page,
- progress page,
- AI training page structure,
- AI topic selection,
- conversation frontend logic,
- backend bridge for AI requests,
- database integration,
- context management,
- responsive refinements.

### Still in progress:
- OpenAI conversation tuning,
- voice mode stabilisation.

### Recommandations from the advisor:
- The AI needs to know:
  - the language,
  - the objectif,
  - the context,
  - the level of the student,
  - the theme/grammar point/both,
  in order to communicate with the student.
- The teacher can put little notes for the AI so that the AI knows the progress during the class.

### Issues:
- The browser used HTTP:
  - The sensitive API (in this case, the mic), doesn't trust the site to allow the user use the microphone
  - We cannot force the site to be a trusted site for other security issues like man-in-the-middle

## Notes

The project evolved during development.
Some initial ideas were simplified after discussion in order to:

- keep the product more coherent,
- reduce unnecessary complexity,
- focus on the student experience,
- prioritise the AI oral practice page.

## Conclusion

This project allowed me to explore behind the scenes of every student portail that has been used for a long time. I could also polish my experience in PHP, SQL, HTML and CSS also obtain new skills such as integrating OpenAI API into our website, JavaScript and many more.
