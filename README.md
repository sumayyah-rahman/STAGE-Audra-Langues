# Audra Languages – AI Student & Assistant Portal

Student and teacher portal project with AI conversational assistant for English oral practice.
The project includes a student area, an AI widget, a teacher portal start, as well as a backend architecture intended to be linked to a conversational API.

## Goal

The aim of the project is to create an integrated learning environment for Audra Languages, with:
- a student portal;
- a teacher portal;
- an AI conversational assistant focused on oral practice;
- a visual coherence with existing portals.

## Current Features

### Student Portal
- login page;
- student dashboard;
- personal information page;
- progress page;
- AI training page;
- conversational widget openable from the interface.

### AI Widget
- opening/closing the widget;
- choice of a conversation theme;
- display of the first message according to the chosen theme;
- sending text messages;
- integration of the browser’s speech synthesis;
- integration of browser voice recognition.

### Backend
- file `chat.php` prepared to link the widget to a conversational API;
- student-side session management;
- project structure designed for a future more complete integration.

## Technologies utilisées

- PHP
- HTML
- CSS
- JavaScript
- Sessions PHP
- API Web Speech (SpeechRecognition / speechSynthesis)
- Intégration API conversationnelle en cours

## Project Structure

- `portail_commun.php` / `portail_eleve.php`login page
- `dashboard_eleve.php`: Student dashboard
- `entrainement_ia_eleve.php`: AI training page
- `progres_eleve.php` progress page
- `info_perso_eleve.php`: personal information
- `cours_prof.php` Start of the teacher portal
- `assets/css/` style sheets
- `assets/js/`: JavaScript scripts
- `assets/api/chat.php`: AI communication backend

## Security

API keys should never be hard-coded in versioned code.
Preferably use environment variables or an unexposed server file.

## Project Status

The project is under development.

Already completed:
- structure of the student portal;
- first version of the conversational widget;
- teacher portal base;
- student-side session logic.

Coming soon:
- full integration of the conversational API;
- connection to real data;
- finalization of the teacher mode;
- improvement of voice mode management;
- overall stabilization of the application.

## Roadmap

- finalize the AI widget;
- integrate the database;
- complete the teacher portal;
- improve responsiveness;
- stabilize voice exchanges.
