# Install game packages at deploy time

Developers install standalone game packages at deploy time. Each game can choose its renderer, and the first graphics-heavy game will use WebAssembly.

Laravel delivers playable content, keeps the answer key server-side, and records attempts and progress. The game package owns its UI and rules. This gives each game the same content and progress boundaries while allowing different play styles.

Platform admins control overall availability in Filament. Parents can turn available games on or off for their household. Disabling a game blocks new runs within its scope, lets active runs finish, and retains progress.
