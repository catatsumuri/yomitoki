# Design Directions

## 前提

現在のアプリは、次の性質を持っている。

- `capture-first`
- 最小単位は `scrap`
- `scrap` は Markdown を正本として持つ
- 親 `scrap` は `slug` を持ち、`/dashboard/{slug}` で開ける
- 子 `scrap` は親の文脈にぶら下がる
- 削除はまず `archive`
- AI は入力を強制せず、後段で metadata や整理を補助する

ここに embedding を入れるとき、単なる「関連記事」よりも、「知識を繋げる力」をどう設計に落とすかが重要になる。

この記事で見えた示唆は次のとおり。

- LLM の強みは単発要約よりも知識接続にある
- 価値は `query 時に毎回ゼロから再発見すること` ではなく、知識が累積することにある
- 維持コストの高い bookkeeping を AI に持たせると、知識ベースが育ちやすい
- `Ingest / Query / Lint` という分け方はプロダクト設計にも転用できる

## 設計の大きな方向性

以下の案は排他的ではない。むしろ、内部設計と外向けの見せ方を分けて組み合わせる前提で考える。

## 1. Scrap Graph 型

### 概要

`scrap` 同士の接続そのものを中心に据える案。

### 見え方

- `scrap` は raw な断片
- 親子は明示リンク
- embedding は暗黙の近接関係を発見する
- AI は補強・反証・重複候補を提案する

### embedding の使い道

- related scraps
- duplicate 候補
- parent 候補
- 孤立 scrap の発見

### 強み

- 「繋げる力」を最も素直に表現できる
- 将来の Query / Lint の基盤になる
- 知識グラフ的な伸び方ができる

### 弱み

- 成果物が見えにくい
- 初見では「結局何ができるのか」が伝わりにくい

## 2. Spec Node 型

### 概要

親 `scrap` を concept page や spec node とみなし、その下に知識を集約する案。

### 見え方

- 親 `scrap` は仕様テーマの核
- 子 `scrap` は補足、要件、論点、反例
- embedding は親の外にある関連断片を引く
- AI は親 `scrap` を育てていく

### embedding の使い道

- 親に関連する断片回収
- 似た仕様テーマの提示
- 未回収の関連事項の発見

### 強み

- 今の UI / URL / 親子構造と自然に噛み合う
- `/dashboard/{slug}` を知識ノードとして扱える
- 仕様書ドラフト生成への導線が明確

### 弱み

- 親 `scrap` の責務が重くなりやすい
- 仕様テーマの切り方が雑だと構造が崩れる

## 3. Ingest / Query / Lint 型

### 概要

Karpathy 的な運用モデルを、そのままプロダクトの機能軸にする案。

### 見え方

- `Ingest`: scrap 保存、添付、子追加
- `Query`: 蓄積済み知識への質問
- `Lint`: 矛盾、孤立、重複、未解決事項の検出

### embedding の使い道

- Query 時の関連回収
- Lint 時の類似・孤立検出
- Ingest 時の既存トピック接続

### 強み

- AI の役割がかなり明快になる
- 「Agentic AI」として説明しやすい
- query 結果や lint 結果を資産化しやすい

### 弱み

- 抽象度が高く、UI 設計を誤ると分かりにくい
- いきなり全部見せると概念過多になる

## 4. Persistent Draft 型

### 概要

仕様書を毎回生成するものではなく、蓄積知識の上で継続的に育つドラフトとみなす案。

### 見え方

- scrap を投入する
- AI が知識を接続する
- 親 `scrap` が育つ
- 仕様書ドラフトが継続的に更新される

### embedding の使い道

- 仕様書に関係する既存断片の回収
- 過去の類似論点の再利用
- ドラフト更新時の関連知識補充

### 強み

- ビジネス価値を説明しやすい
- 「persistent, compounding artifact」の発想と合う
- ハッカソン審査の実務性に刺さりやすい

### 弱み

- draft と scrap の境界が曖昧になる可能性がある
- どの時点で「仕様書」とみなすかの定義が必要

## embedding の使い方の切り口

上の設計案とは別に、embedding の投入ポイントにもいくつか方向性がある。

## A. Similarity First

最初に類似表示から始める案。

- related scraps
- duplicate 候補
- parent 候補提案

### 強み

- 実装が軽い
- 効果が見えやすい

### 弱み

- そのままだと「よくある関連記事」で終わりやすい

## B. Context First

AI の文脈補強を先に主用途にする案。

- `AI organize` に関連 scrap を渡す
- 仕様書生成時の材料回収
- 過去論点の参照

### 強み

- AI 出力品質に直結する
- 実務価値が高い

### 弱み

- UI 上では効果が少し見えにくい

## C. Lint First

知識ベースの健康診断を先に作る案。

- 孤立 scrap
- 高類似だが未接続
- 別親にある近似トピック
- 矛盾候補

### 強み

- Agentic AI らしさが強い
- bookkeeping を AI に持たせる発想に近い

### 弱み

- 初期フェーズでは少し高度に見える
- 判定理由の説明が必要

## D. Query First

蓄積済み知識への質問を先に前面に出す案。

- この仕様に近い過去議論は？
- 未解決事項は？
- この要件への反証はあるか？

### 強み

- LLM Wiki 的な価値が分かりやすい
- query を資産化しやすい

### 弱み

- まず知識が溜まっていないと弱い
- capture-first の初期体験とは少し距離がある

## ひとまずの組み合わせ候補

### 候補 1

- 外向き: `Spec Node`
- 内部: `Scrap Graph`
- embedding: `Similarity First`

最も自然で、今の実装にも乗せやすい。

### 候補 2

- 外向き: `Persistent Draft`
- 内部: `Spec Node + Scrap Graph`
- embedding: `Context First`

仕様書ドラフト生成を主役にしたいときに強い。

### 候補 3

- 外向き: `Agentic Spec Assistant`
- 内部: `Ingest / Query / Lint`
- embedding: `Context First + Lint First`

AI の役割を前面に出したいときに強い。

### 候補 4

- 外向き: `LLM Wiki の業務仕様版`
- 内部: `Scrap Graph + Ingest / Query / Lint`
- embedding: `Similarity First + Query First`

知識ベースとしての価値を押し出す案。

## 現時点のおすすめ

現状の実装と一番噛み合うのは次の組み合わせである。

- 外向き: `Persistent Draft + Spec Node`
- 内部: `Scrap Graph + Ingest / Query / Lint`

この形なら、

- `capture-first` を壊さない
- 親 `scrap` を仕様テーマの知識ノードとして育てられる
- embedding を「関連表示」から「文脈補強」「知識接続」に伸ばせる
- ハッカソン向けには仕様書ドラフト生成アプリとして説明しやすい

## 要約

embedding をどう使うかは、単なる「似た記事表示」で終わらせるか、「知識を繋げて持続的な成果物を育てる」方向に持っていくかで意味が大きく変わる。

現時点では、次の理解が一番筋が良い。

- `scrap` は raw な断片
- 親 `scrap` は仕様テーマの知識ノード
- embedding は知識接続と文脈補強のために使う
- 仕様書は毎回ゼロから生成するのではなく、蓄積の上で育てる

## Route 設計

動線を成立させるには、route の責務分離が重要になる。

現在の延長線上で考えると、次の分け方が自然である。

### `/dashboard`

capture の入口。

役割:

- 新しい `scrap` を素早く投入する
- 直近の親 `scrap` を入口として見る
- 保存後に次の行動へ進む

この画面では、最重要なのは `書き始めること` である。
情報を読むことや深く整理することは主役にしない。

将来的にここで返すもの:

- 保存直後の related scraps
- 親候補
- `Organize now` への導線

### `/dashboard/{slug}`

親 `scrap` の知識ノード画面。

役割:

- 親 `scrap` を読む
- 子 `scrap` を確認 / 追加する
- related scraps を見る
- organize 結果を見る
- 仕様書ドラフト化へ進む

この画面は「記事詳細」ではなく、「spec node を育てる場所」とみなす。

### `/archives`

退避済み `scrap` の一覧。

役割:

- archive 済みの親 / 子 `scrap` を確認する
- restore する
- 完全削除する

ここは作業の主導線ではなく、保守・整理のための補助画面とする。

## 将来的な route 拡張候補

現時点では route を増やしすぎない方がよいが、設計上は次の拡張余地がある。

### `/dashboard/{slug}/organize`

親 `scrap` に対して organize を集中的に行う画面。

候補機能:

- related scraps の精査
- 親への統合候補確認
- open questions の確認
- AI organize の実行履歴

ただし初期段階では、`/dashboard/{slug}` に同居させた方が流れは自然である。

### `/dashboard/{slug}/draft`

仕様書ドラフト専用画面。

候補機能:

- 章立て生成
- overview / requirements / open questions の編集
- 元になった scraps の参照

これも最初から分離せず、まずは親 `scrap` の延長線上で扱う方がよい。

### `/queries`

知識ベースへの質問結果を蓄積する画面。

候補機能:

- query 履歴の一覧
- query 回答の資産化
- 親 `scrap` ごとの query 再訪

これは `Ingest / Query / Lint` を前面化する段階で意味を持つ。

### `/lint`

知識ベースの健康診断画面。

候補機能:

- 孤立 scrap
- 高類似だが未接続の scrap
- 矛盾候補
- 未解決事項

初手では不要だが、このアプリの AI らしさを強く出せる route ではある。

## route 設計の基本方針

初期は route を増やしすぎない。

- `/dashboard`: capture
- `/dashboard/{slug}`: spec node
- `/archives`: 退避管理

まずはこの 3 本で、

- 書く
- 繋がる
- 育てる

の導線を成立させる。

その後に必要が出てから、

- organize
- draft
- query
- lint

を独立 route として切り出す。

## Organize 後の所属

`scrap` を organize した結果は、一時的な提案ではなく、状態として反映してよい。

考え方は次のとおり。

- 新規 `scrap` は最初は未所属で作られる
- organize の時点で、既存の親 `scrap` に所属させるか、新しい親 `scrap` を作るかを決める
- organize 後は、その親 `scrap` の文脈で扱う
- 後から所属変更はできるようにする

この仕様にする理由:

- raw scrap を最初から構造化しなくてよい
- organize が単なる閲覧ではなく、知識構造を更新する操作になる
- 親 `scrap` を知識ノードとして育てやすい
- 仕様書ドラフト生成時の対象集合が明確になる

ここで区別すべきこと:

- `parent_id` は所属を表す
- embedding 由来の related scraps は推薦を表す

この 2 つは別概念として扱う。

## Artifact の考え方

organize の先にある成果物は、直接編集可能ではあるが、主更新経路は `追加 scrap -> 再生成` に寄せる方が筋が良い。

### 基本方針

- `artifact` は読める
- `artifact` は編集もできる
- ただし通常の更新は、追加された `scrap` を材料に再生成する

### この方針の利点

- 情報の流入元が `scrap` に揃う
- 何が根拠で更新されたかを追いやすい
- `capture-first` の体験を壊さない
- `persistent draft` として成果物を育てやすい

### 役割分担

- `scrap`: raw な断片、補足、要件、論点、反例
- `artifact`: `scrap` 群から構築される成果物

このとき、設計の考え方としては:

- 正本は `scrap` 群
- `artifact` はそこから生成・更新される projection

とみなすのが自然である。

### 更新の考え方

初期段階では、次の方針がよい。

- 軽微修正のために artifact の直接編集は許可する
- ただし主更新経路は `追加 scrap -> regenerate`
- 後から必要であれば、全再生成ではなくセクション単位再生成へ発展させる

たとえば将来的には、次のような更新単位がありうる。

- overview だけ更新
- requirements だけ更新
- open questions だけ更新

## 現時点での整理

ここまでの議論を踏まえると、構造は次のように整理できる。

- `scrap` は raw input の単位
- organize は scrap を親 `scrap` 配下へ所属させる操作
- 親 `scrap` は仕様テーマの知識ノード
- embedding は関連断片を見つけ、接続するために使う
- `artifact` は知識ノードの上で育つ成果物
- 主更新経路は `追加 scrap -> 再生成`

この構造であれば、

- まず雑に放り込める
- 後から接続できる
- 接続後は知識ノードとして蓄積できる
- 最終的に仕様書ドラフトへ昇格できる

という流れが一貫する。

## AI Autopilot ではなく AI Copilot

このアプリの思想は、`AI autopilot` ではなく `AI copilot` として整理するのが最も自然である。

役割分担は次のとおり。

- AI: 候補を出す
- 人間: 採用する
- システム: 採用結果を状態として保存する

これは organize にも artifact 編集にもそのまま当てはまる。

### 構造としての整理

- `scrap`
  正本の入力単位
- `organize`
  AI による構造化提案
- `approval / edit`
  人間による媒介
- `parent_id / artifact`
  人間の判断を反映した状態

この前提なら、AI は提案者であり、決定者ではない。
状態として永続化されるのは、人間が採用した結果だけである。

## artifact 編集の位置づけ

artifact は projection ではあるが、人間による直接編集を許してよい。

これは思想に反しない。むしろ、

- AI が下書きを出す
- 人間が確定稿へ近づける

という流れの方が自然である。

したがって、artifact 編集は例外ではなく、正式な操作として扱う。

ただし、主更新経路は引き続き `追加 scrap -> 再生成` に置く。

## 再生成時の衝突

artifact を人間が編集できるなら、再生成時に衝突を扱う必要がある。

最低限必要な考え方は次のとおり。

- artifact に手動編集があるかを識別できる
- regenerate 時に無言で上書きしない
- 人間に選択させる

たとえば、再生成時には次のような確認が必要になる。

- 新しい版として生成する
- 現在の編集を保持して差分提案する
- 上書きする

初期段階ではすべてを実装しなくてもよいが、少なくとも「手動編集がある artifact を無言で壊さない」という原則は守るべきである。

## versioning の必要性

AI 再生成と人間編集が共存するなら、artifact の version は早めに持った方がよい。

最小案:

- `artifact_versions`
- `id`
- `artifact_id`
- `body_markdown`
- `source: generated / human_edited / regenerated`
- `created_at`

これにより、

- 人間編集後の巻き戻し
- 再生成前後の比較
- どこから現在版ができたかの追跡

が可能になる。

version を持たない場合、AI 再生成と人間編集が衝突した時に復旧不能になりやすい。

## 現時点の結論

方針は次のように固定するのがよい。

- AI は決めない
- AI は候補を出す
- 人間が媒介する
- 媒介された結果だけが状態になる
- 成果物は人間が編集できる
- ただし再生成時は人間編集を壊さない

この思想に立つと、このアプリは

`AI が勝手に整理するツール`

ではなく、

`人間が AI の提案を採用しながら、知識と成果物を育てるツール`

として説明できる。
