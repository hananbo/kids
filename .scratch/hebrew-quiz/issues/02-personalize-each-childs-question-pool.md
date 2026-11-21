# 02: Personalize each child’s question pool

**What to build:** Parents choose a Learning Age, Subjects, and starting Quiz Level for each Child Profile. The quiz uses those settings to pick a complete run, and Filament identifies gaps the parent can fill.

**Blocked by:** 01 (Play the first Hebrew quiz)

**Status:** ready-for-agent

- [ ] A Question can have multiple Subjects, a Target Age from 1 to 18, and a Difficulty from 1 to 10. Parents can set each Child Profile's Subjects, Learning Age, and starting Quiz Level independently. Changing Subjects preserves existing answers and progress.
- [ ] Each run uses eight distinct approved Questions, filtered by the chosen Subjects and a Target Age at or below the child's Learning Age. Groups contain three, three, and two Questions. The group Difficulty ranges are 1 / 2 / 3 for Level 1, 4 / 5 / 6 for Level 2, and 7 / 8–9 / 10 for Level 3.
- [ ] Within each group, unseen Questions come first, ordered by closeness of Target Age to Learning Age. Remaining places go to the least recently seen eligible Questions.
- [ ] Launch content supplies two full runs at each configured starting level, with at least six, six, and four distinct eligible Questions across the respective groups. An undersupplied group blocks a new run and the parent can see which group is short in Filament.
- [ ] Deterministic coverage exercises the selection through the normal quiz entry point, including age and Subject filters, repeats, all three level mappings, and a group shortage.
