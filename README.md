# Blink Block

WordPrsss 7.0 から追加される「PHPだけのブロック」で、
かつて HTML にあった `<blink>` タグの点滅効果を再現したブロックです。

## ファイル構成

```
php-only-blink-block/
├── blink-block.php   # メインのプラグインファイル
├── style.css         # 点滅アニメーション用 CSS
└── README.md
```

---

## インストール方法

1. `php-only-blink-block` フォルダを `wp-content/plugins/` に配置する
2. WordPress 管理画面の **プラグイン** → **インストール済みプラグイン** から「Blink Block」を有効化する
3. 投稿や固定ページの編集画面で、ブロックエディターのインサーターから「Blink」を追加する

**要件**: WordPress 7.0 以上、PHP 8.3 以上

---

## コードの流れ (初心者向け)

このプラグインは大きく3つの処理で成り立っています。

### 1. ブロック登録

WordPress に「このブロックが存在する」と伝え、設定項目 (属性) を定義します。`init` フックのタイミングで実行され、ブロックエディターのインサーターに表示されます。

```php
add_action( 'init', 'blink_block_register' );
```

`register_block_type` の第1引数はブロックのスラッグ (一意の識別子)。`名前空間/ブロック名` の形式です。

```php
register_block_type(
	'original-plugin/blink',
	array(
		'title'       => 'Blink', // ブロックのタイトル
		'description' => 'blink タグを再現した点滅ブロックです。', // インサーターに表示される説明文
		'category'    => 'text',   // ブロックのカテゴリー
		'icon'        => 'star-half', // ブロックのアイコン (Dashicons の名前。インサーターやツールバーに表示される)
		// ...
	)
);
```

**attributes** でブロック固有の設定項目を定義すると、右サイドバーの設定パネルに表示されます。

- `type: 'string'` → 自由入力のテキスト欄
- `enum` を指定 → セレクトボックスになる

```php
'attributes' => array(
	'text' => array(
		'type'    => 'string',
		'default' => 'このテキストが点滅します',
		'label'   => 'テキスト',
	),
	'speed' => array(
		'type'    => 'string',
		'enum'    => array( '遅い', '通常', '速い', 'とても速い' ),
		'default' => '通常',
		'label'   => '点滅速度',
	),
	'mode' => array(
		'type'    => 'string',
		'enum'    => array( '表示/非表示', '薄くする' ),
		'default' => '表示/非表示',
		'label'   => '点滅方式',
	),
),
```

**supports** でブロックが利用できる共通機能を宣言します。`true` にすると、色・余白・文字などの設定がブロックに追加されます。

```php
'supports' => array(
	'align'     => array( 'wide', 'full' ),
	'color'     => array( 'text' => true, 'background' => true ),
	'spacing'   => array( 'padding' => true, 'margin' => true ),
	'typography' => array( 'fontSize' => true, 'lineHeight' => true, 'textAlign' => true ),
	'shadow'    => true
),
```

**render_callback** に指定した関数が、ページ表示時に呼ばれます。返り値の HTML がそのまま出力されます。

```php
'render_callback' => 'blink_block_render',
```

---

### 2. レンダリング処理

投稿や固定ページを表示するとき、`blink_block_render` が呼ばれます。`$attributes` にはブロックに設定した値が入っています。

速度ラベルを CSS アニメーションの周期 (ミリ秒) に変換しています。1周期 = 表示時間 + 非表示時間 なので、元の間隔の2倍の値を使います。

```php
$speed_map = array(
	'遅い'      => 2400,
	'通常'      => 1600,
	'速い'      => 1000,
	'とても速い' => 500,
);

$duration_ms = $speed_map[ $attributes['speed'] ] ?? 1600;  // ?? は「未設定なら 1600 を使う」
```

点滅方式に応じて CSS クラスを切り替え、`--blink-duration` という CSS 変数でアニメーション速度を渡します。

```php
$mode_class = ( $mode === '薄くする' ) ? 'blink-block__text--opacity' : 'blink-block__text--visibility';
$style      = sprintf( '--blink-duration: %dms', $duration_ms );
```

`get_block_wrapper_attributes` は、ブロックサポート (色・余白・フォントなど) で設定された `class` や `style` を自動で付与します。

```php
$wrapper_attrs = get_block_wrapper_attributes(
	array( 'class' => 'blink-block' )
);
```

`esc_attr` / `esc_html` で出力時にエスケープし、XSS などのセキュリティリスクを防ぎます。

```php
return sprintf(
	'<div %s><span class="blink-block__text %s" style="%s">%s</span></div>',
	$wrapper_attrs,
	esc_attr( $mode_class ),
	esc_attr( $style ),
	esc_html( $attributes['text'] )
);
```

---

### 3. スタイル登録

点滅アニメーション用の CSS を、フロント (サイト表示) とエディター (編集画面) の両方に読み込みます。

**フロント用**: `wp_enqueue_block_style` を使うと、ブロックがページに含まれるときだけ CSS を読み込めます (無駄な読み込みを防ぐ)。

```php
wp_enqueue_block_style(
	'original-plugin/blink',
	array(
		'handle' => 'blink-block-style',
		'src'    => plugin_dir_url( __FILE__ ) . 'style.css',
		'path'   => plugin_dir_path( __FILE__ ) . 'style.css',
		'ver'    => BLINK_BLOCK_VERSION,   // キャッシュ対策
	)
);
```

**エディター用**: 編集画面でもブロックの見た目を正しく表示するため、`enqueue_block_editor_assets` フックで同じ CSS を読み込みます。

```php
add_action( 'enqueue_block_editor_assets', 'blink_block_editor_styles' );
```

---

## style.css の仕組み

PHP から渡される `--blink-duration` とモード別クラス (`--visibility` / `--opacity`) を使って、点滅を制御しています。

```css
.blink-block__text--visibility {
	animation-name: blink-visibility;
	animation-duration: var(--blink-duration, 1.6s);
}

@keyframes blink-visibility {
	0%, 100% { opacity: 1; }
	50%     { opacity: 0; }
}

.blink-block__text--opacity {
	animation-name: blink-opacity;
	animation-duration: var(--blink-duration, 1.6s);
}

@keyframes blink-opacity {
	0%, 100% { opacity: 1; }
	50%     { opacity: 0.2; }
}
```

---

## その他のポイント

- **ABSPATH**: WordPress 以外から直接アクセスされた場合に処理を止めるセキュリティ対策
- **add_action**: 指定したフックのタイミングで関数を実行するよう登録する
- **get_file_data**: プラグインファイルのヘッダーから Version などを取得する


## アップデート情報

### 1.0.1
- レンダリング処理の三項演算子を if-else に変更 (可読性の向上)
- コード内のコメントを README.md に移動

### 1.0.0
- 初回リリース