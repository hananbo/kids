1 platform of curated questions for my kids on different subjects with 1 database but can be utilized and served for many types of games, all served on web but should be mobile first spa and utilize up to date performant graphics with webassembly. should support multilanguage and RTL from day one. the backend is built on php laravel mysql with laravel filament 5 as the backoffice CMS. each game should be a standalone plugin package that can be installed and enabled disabled. write docs and instructions so it will be easier for ai to add new games later on. we will start with hebrew

mini solo games:
- wheel of fortune
- who wants to be a millionaire
- math gate runner
- wordle - but with the meaning at the end and hints
mini multiplayer games:
- draw to guess the word - like  https://skribbl.io/


it's important that the questions and answers will support:
- rich data types - text and numbers but also photos, videos, audios for bot question and its answers
- translations - a single question and its answers can be used in many languages. the data should support it in a smart way so questions and answer always show the user's language but doesn't persist duplicate data per language when there's no need to. for example for numbers. or for example if the picture or video are generic for all languages. i expect an architecture where we fallback to the default question or answer if there's no language override.
- age and effort - marking each question can be rated by a number of age - 1-18 and number of level 1-10
- status - indicates the question flow:
  - new - question and answers were just inserted by ai or from web with its raw data of links, assets, references.
  - assesment - ai asses the appropriate age and level, adds the subjects and associate the questions with relevant games.
  - enrichment - ai enriches the question and answers with:
    -  visuals or generated media - only if necessary
    - hints
    - explanations
    - references
    - links
  - draft - user of admin or parent role - review and edit
  - publishing - automated process where backend takes the question and its answers and prepare it for the games, possible optional flows:
	    - translate to target languages
	    - generate relevant game specific assets
- published - question and answer it ready for the 

subjects - each question can be tagged with multiple subjects. the subjects system will be used to categorize the subjects of the question


all games must incentive the kids to continue and get better, acheive milestones or higher ranks and unlock new levels or difficulties.

Filamanet 5 cms must support an a flow that goes and generates a pool of questions from either the web or by ai

Filament 5 must support a great ui that lets the parent convinently audit and edit the questions and answers and move them for publishing

