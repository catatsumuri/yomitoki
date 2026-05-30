# Embedding Plan

## 目的

このアプリにおける embedding の最初の用途は、検索機能そのものではなく、`scrap` を整理し仕様書へ育てるための関連断片回収に置く。

最初の実装ターゲットは `related scraps` である。

## 前提

- アプリは `capture-first` を採用している
- 最小単位は `scrap`
- 親 `scrap` は `slug` を持ち、`/dashboard/{slug}` で開ける
- 子 `scrap` は親の文脈にぶら下がる
- 将来的な主用途は、断片から仕様書ドラフトを構築すること

## 位置づけ

embedding は「関連記事」のためではなく、次の用途のために使う。

- 今書いた `scrap` と近い既存断片を見つける
- `AI organize` の文脈を補強する
- 親 `scrap` から仕様書を組み立てるときの材料を回収する

## 段階的な導入

### Phase 1: Related Scraps

最初に実装する。

- `scrap` 保存時に embedding を生成する
- `scrap` 詳細表示時に近い `scrap` を検索する
- `/dashboard/{slug}` に `Related scraps` を表示する

初期仕様:

- 対象は親 `scrap` のみ
- 件数は 5 件
- 除外対象は自分自身と `archived`
- 表示場所は親 `scrap` 本文の下

目的:

- embedding の効果を最短で可視化する
- 後続の AI 機能に使う検索基盤を先に作る

### Phase 2: AI Organize の補強

`AI organize on save` 実行時に、保存対象 `scrap` だけでなく関連 `scrap` も文脈として渡す。

AI が補助する対象:

- title
- summary
- tags
- open questions
- parent candidate

目的:

- 単発の本文だけでは弱い整理結果を改善する
- 過去の近い議論を踏まえた補完を可能にする

### Phase 3: 仕様書ドラフト生成

親 `scrap` を起点に、仕様書ドラフト生成へ使う。

入力候補:

- 親 `scrap`
- 子 `scrap`
- embedding で拾った関連 `scrap`
- 添付ファイル由来のテキスト

出力候補:

- overview
- background
- goals
- requirements
- non-goals
- open questions

目的:

- embedding を単なる一覧補助ではなく、仕様書構築の材料回収エンジンとして使う

### Phase 4: 親候補提案

必要であれば後から実装する。

- 新しい `scrap` が既存のどの親に近いかを提案する
- `新規親として扱う` と `既存親へぶら下げる` の判断を補助する

これは UX を少し重くするので、初手では入れない。

## データ設計の方針

`scraps` に embedding 関連の属性を持たせる。

候補:

- `embedding`
- `embedding_model`
- `embedding_generated_at`

親子ともに embedding を持てるようにするが、最初の類似表示は親 `scrap` を優先する。

## UI 方針

`/dashboard/{slug}` で次の流れを作る。

- 親 `scrap` を読む
- 子 `scrap` を確認する
- `Related scraps` を見る
- そこから `AI organize` または仕様書化へ進む

つまり、`related scraps` は読了後の推薦ではなく、整理と構築のための作業補助として置く。

## 初期の割り切り

最初は以下をやらない。

- 汎用ベクトル検索画面
- 全文検索の置き換え
- チャット型ナレッジ検索 UI
- 子 `scrap` を単独で URL 化すること

まずは「親 `scrap` を育てるための関連断片提示」に絞る。

## 実装順

1. `scrap` 保存時に embedding を生成する
2. 類似 `scrap` を返す query を作る
3. `/dashboard/{slug}` に `Related scraps` を表示する
4. `AI organize` に related context を渡す
5. 仕様書ドラフト生成へつなぐ

## 要約

最初の embedding 活用はこれで十分である。

`embedding を使って related scraps を出し、その related scraps を AI organize と仕様書ドラフト生成の文脈に使う`
