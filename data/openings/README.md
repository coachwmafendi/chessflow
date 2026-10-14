# Opening names

`a.tsv` … `e.tsv` come from [lichess-org/chess-openings](https://github.com/lichess-org/chess-openings)
(CC0 1.0, public domain). `node tools/build-openings.cjs` turns them into `public/openings/openings.json`:
`{ "<EPD>": ["C50", "Italian Game"], … }`, keyed by position so transpositions are recognised.
Re-run it after updating the TSV files.
