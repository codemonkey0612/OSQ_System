<?php
/**
 * 57-item questionnaire definitions.
 *
 * Ministry of Health, Labour and Welfare approved stress check items
 * with bilingual support (Japanese/English).
 *
 * @package OSQ
 */

namespace OSQ\Questionnaire;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuestionDefinitions
 *
 * Static data class for the 57-item stress check questionnaire.
 */
class QuestionDefinitions {

	/**
	 * Section identifiers.
	 */
	const SECTION_A = 'A'; // Work-related stressors (17 items).
	const SECTION_B = 'B'; // Physical/mental condition (29 items).
	const SECTION_C = 'C'; // Social support (9 items).
	const SECTION_D = 'D'; // Satisfaction (2 items, reference only).

	/**
	 * Total number of scored items (A+B+C).
	 */
	const SCORED_ITEM_COUNT = 57;

	/**
	 * Get response options for a given section.
	 *
	 * @param string $section Section identifier (A, B, C, D).
	 * @return array
	 */
	public static function get_response_options( $section ) {
		switch ( $section ) {
			case self::SECTION_A:
				return array(
					1 => array( 'ja' => 'そうだ', 'en' => 'Strongly agree' ),
					2 => array( 'ja' => 'まあそうだ', 'en' => 'Somewhat agree' ),
					3 => array( 'ja' => 'ややちがう', 'en' => 'Somewhat disagree' ),
					4 => array( 'ja' => 'ちがう', 'en' => 'Disagree' ),
				);
			case self::SECTION_B:
				return array(
					1 => array( 'ja' => 'ほとんどなかった', 'en' => 'Almost never' ),
					2 => array( 'ja' => 'ときどきあった', 'en' => 'Sometimes' ),
					3 => array( 'ja' => 'しばしばあった', 'en' => 'Often' ),
					4 => array( 'ja' => 'ほとんどいつもあった', 'en' => 'Almost always' ),
				);
			case self::SECTION_C:
				return array(
					1 => array( 'ja' => '非常に', 'en' => 'Very much' ),
					2 => array( 'ja' => 'かなり', 'en' => 'Considerably' ),
					3 => array( 'ja' => '多少', 'en' => 'Somewhat' ),
					4 => array( 'ja' => '全くない', 'en' => 'Not at all' ),
				);
			case self::SECTION_D:
				return array(
					1 => array( 'ja' => '満足', 'en' => 'Satisfied' ),
					2 => array( 'ja' => 'まあ満足', 'en' => 'Somewhat satisfied' ),
					3 => array( 'ja' => 'やや不満足', 'en' => 'Somewhat dissatisfied' ),
					4 => array( 'ja' => '不満足', 'en' => 'Dissatisfied' ),
				);
			default:
				return array();
		}
	}

	/**
	 * Get all 57 questions + 2 reference items.
	 *
	 * @return array Array of question definitions.
	 */
	public static function get_questions() {
		return array_merge(
			self::get_section_a(),
			self::get_section_b(),
			self::get_section_c(),
			self::get_section_d()
		);
	}

	/**
	 * Get questions for a specific section only.
	 *
	 * @param string $section
	 * @return array
	 */
	public static function get_section_questions( $section ) {
		switch ( $section ) {
			case self::SECTION_A: return self::get_section_a();
			case self::SECTION_B: return self::get_section_b();
			case self::SECTION_C: return self::get_section_c();
			case self::SECTION_D: return self::get_section_d();
			default: return array();
		}
	}

	/**
	 * Get the list of reverse-scored item IDs per section.
	 *
	 * Reverse items: score 1→4, 2→3, 3→2, 4→1.
	 *
	 * @return array Keyed by section.
	 */
	public static function get_reverse_items() {
		return array(
			'A' => array( 1, 2, 3, 4, 5, 6, 7, 11, 12, 13, 15 ),
			'B' => array( 1, 2, 3 ),
			'C' => array( 1, 2, 3, 4, 5, 6, 7, 8, 9 ), // ALL C items.
		);
	}

	/**
	 * Section A: Work-related stressors (17 items).
	 *
	 * @return array
	 */
	private static function get_section_a() {
		return array(
			array(
				'id'         => 'A-1',
				'section'    => 'A',
				'number'     => 1,
				'text_ja'    => '非常にたくさんの仕事をしなければならない',
				'text_en'    => 'I have an extremely large amount of work to do',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-2',
				'section'    => 'A',
				'number'     => 2,
				'text_ja'    => '時間内に仕事が処理しきれない',
				'text_en'    => 'I cannot finish my work within the allotted time',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-3',
				'section'    => 'A',
				'number'     => 3,
				'text_ja'    => '一生懸命働かなければならない',
				'text_en'    => 'I have to work very hard',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-4',
				'section'    => 'A',
				'number'     => 4,
				'text_ja'    => 'かなり注意を集中する必要がある',
				'text_en'    => 'I need to concentrate a great deal',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-5',
				'section'    => 'A',
				'number'     => 5,
				'text_ja'    => '高度の知識や技術が必要なむずかしい仕事だ',
				'text_en'    => 'My work requires advanced knowledge and skills',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-6',
				'section'    => 'A',
				'number'     => 6,
				'text_ja'    => '勤務時間中はいつも仕事のことを考えていなければならない',
				'text_en'    => 'I must always think about work during working hours',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-7',
				'section'    => 'A',
				'number'     => 7,
				'text_ja'    => 'からだを大変よく使う仕事だ',
				'text_en'    => 'My work requires a lot of physical effort',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-8',
				'section'    => 'A',
				'number'     => 8,
				'text_ja'    => '自分のペースで仕事ができる',
				'text_en'    => 'I can work at my own pace',
				'is_reverse' => false,
			),
			array(
				'id'         => 'A-9',
				'section'    => 'A',
				'number'     => 9,
				'text_ja'    => '自分で仕事の順番・やり方を決めることができる',
				'text_en'    => 'I can decide the order and method of my work',
				'is_reverse' => false,
			),
			array(
				'id'         => 'A-10',
				'section'    => 'A',
				'number'     => 10,
				'text_ja'    => '職場の仕事の方針に自分の意見を反映できる',
				'text_en'    => 'I can reflect my opinions in workplace policies',
				'is_reverse' => false,
			),
			array(
				'id'         => 'A-11',
				'section'    => 'A',
				'number'     => 11,
				'text_ja'    => '自分の技能や知識を仕事で使うことが少ない',
				'text_en'    => 'I rarely get to use my skills and knowledge at work',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-12',
				'section'    => 'A',
				'number'     => 12,
				'text_ja'    => '私の部署内で意見のくい違いがある',
				'text_en'    => 'There are disagreements within my department',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-13',
				'section'    => 'A',
				'number'     => 13,
				'text_ja'    => '私の部署と他の部署とはうまがあわない',
				'text_en'    => 'My department does not get along with other departments',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-14',
				'section'    => 'A',
				'number'     => 14,
				'text_ja'    => '私の職場の雰囲気は友好的である',
				'text_en'    => 'The atmosphere in my workplace is friendly',
				'is_reverse' => false,
			),
			array(
				'id'         => 'A-15',
				'section'    => 'A',
				'number'     => 15,
				'text_ja'    => '私の職場の作業環境（騒音、照明、温度、換気など）はよくない',
				'text_en'    => 'My workplace environment (noise, lighting, temperature, ventilation, etc.) is poor',
				'is_reverse' => true,
			),
			array(
				'id'         => 'A-16',
				'section'    => 'A',
				'number'     => 16,
				'text_ja'    => '仕事の内容は自分にあっている',
				'text_en'    => 'My work suits me well',
				'is_reverse' => false,
			),
			array(
				'id'         => 'A-17',
				'section'    => 'A',
				'number'     => 17,
				'text_ja'    => '働きがいのある仕事だ',
				'text_en'    => 'My work is rewarding',
				'is_reverse' => false,
			),
		);
	}

	/**
	 * Section B: Physical and mental condition (29 items).
	 *
	 * @return array
	 */
	private static function get_section_b() {
		return array(
			array(
				'id'         => 'B-1',
				'section'    => 'B',
				'number'     => 1,
				'text_ja'    => '活気がわいてくる',
				'text_en'    => 'I feel full of energy',
				'is_reverse' => true,
			),
			array(
				'id'         => 'B-2',
				'section'    => 'B',
				'number'     => 2,
				'text_ja'    => '元気がいっぱいだ',
				'text_en'    => 'I am full of vitality',
				'is_reverse' => true,
			),
			array(
				'id'         => 'B-3',
				'section'    => 'B',
				'number'     => 3,
				'text_ja'    => 'いきいきしている',
				'text_en'    => 'I feel lively',
				'is_reverse' => true,
			),
			array(
				'id'         => 'B-4',
				'section'    => 'B',
				'number'     => 4,
				'text_ja'    => '怒りを感じる',
				'text_en'    => 'I feel angry',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-5',
				'section'    => 'B',
				'number'     => 5,
				'text_ja'    => '内心腹立たしい',
				'text_en'    => 'I feel irritated inside',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-6',
				'section'    => 'B',
				'number'     => 6,
				'text_ja'    => 'イライラしている',
				'text_en'    => 'I feel frustrated',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-7',
				'section'    => 'B',
				'number'     => 7,
				'text_ja'    => 'ひどく疲れた',
				'text_en'    => 'I feel extremely tired',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-8',
				'section'    => 'B',
				'number'     => 8,
				'text_ja'    => 'へとへとだ',
				'text_en'    => 'I feel exhausted',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-9',
				'section'    => 'B',
				'number'     => 9,
				'text_ja'    => 'だるい',
				'text_en'    => 'I feel fatigued',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-10',
				'section'    => 'B',
				'number'     => 10,
				'text_ja'    => '気がはりつめている',
				'text_en'    => 'I feel tense',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-11',
				'section'    => 'B',
				'number'     => 11,
				'text_ja'    => '不安だ',
				'text_en'    => 'I feel anxious',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-12',
				'section'    => 'B',
				'number'     => 12,
				'text_ja'    => '落着かない',
				'text_en'    => 'I feel restless',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-13',
				'section'    => 'B',
				'number'     => 13,
				'text_ja'    => 'ゆううつだ',
				'text_en'    => 'I feel depressed',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-14',
				'section'    => 'B',
				'number'     => 14,
				'text_ja'    => '何をするのも面倒だ',
				'text_en'    => 'I find everything troublesome',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-15',
				'section'    => 'B',
				'number'     => 15,
				'text_ja'    => '物事に集中できない',
				'text_en'    => 'I cannot concentrate on things',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-16',
				'section'    => 'B',
				'number'     => 16,
				'text_ja'    => '気分が晴れない',
				'text_en'    => 'I feel gloomy',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-17',
				'section'    => 'B',
				'number'     => 17,
				'text_ja'    => '仕事が手につかない',
				'text_en'    => 'I cannot get into my work',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-18',
				'section'    => 'B',
				'number'     => 18,
				'text_ja'    => '悲しいと感じる',
				'text_en'    => 'I feel sad',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-19',
				'section'    => 'B',
				'number'     => 19,
				'text_ja'    => 'めまいがする',
				'text_en'    => 'I feel dizzy',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-20',
				'section'    => 'B',
				'number'     => 20,
				'text_ja'    => '体のふしぶしが痛む',
				'text_en'    => 'My body aches (joints, etc.)',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-21',
				'section'    => 'B',
				'number'     => 21,
				'text_ja'    => '頭が重かったり頭痛がする',
				'text_en'    => 'My head feels heavy or I have headaches',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-22',
				'section'    => 'B',
				'number'     => 22,
				'text_ja'    => '首筋や肩がこる',
				'text_en'    => 'My neck and shoulders feel stiff',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-23',
				'section'    => 'B',
				'number'     => 23,
				'text_ja'    => '腰が痛い',
				'text_en'    => 'I have lower back pain',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-24',
				'section'    => 'B',
				'number'     => 24,
				'text_ja'    => '目が疲れる',
				'text_en'    => 'My eyes feel tired',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-25',
				'section'    => 'B',
				'number'     => 25,
				'text_ja'    => '動悸や息切れがする',
				'text_en'    => 'I have palpitations or shortness of breath',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-26',
				'section'    => 'B',
				'number'     => 26,
				'text_ja'    => '胃腸の具合が悪い',
				'text_en'    => 'I have stomach/intestinal problems',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-27',
				'section'    => 'B',
				'number'     => 27,
				'text_ja'    => '食欲がない',
				'text_en'    => 'I have no appetite',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-28',
				'section'    => 'B',
				'number'     => 28,
				'text_ja'    => '便秘や下痢をする',
				'text_en'    => 'I have constipation or diarrhea',
				'is_reverse' => false,
			),
			array(
				'id'         => 'B-29',
				'section'    => 'B',
				'number'     => 29,
				'text_ja'    => 'よく眠れない',
				'text_en'    => 'I cannot sleep well',
				'is_reverse' => false,
			),
		);
	}

	/**
	 * Section C: Social support (9 items).
	 *
	 * @return array
	 */
	private static function get_section_c() {
		return array(
			array(
				'id'         => 'C-1',
				'section'    => 'C',
				'number'     => 1,
				'text_ja'    => '上司はどのくらい気軽に話ができますか',
				'text_en'    => 'How easily can you talk to your supervisor?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-2',
				'section'    => 'C',
				'number'     => 2,
				'text_ja'    => '上司はどのくらい頼りになりますか',
				'text_en'    => 'How reliable is your supervisor?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-3',
				'section'    => 'C',
				'number'     => 3,
				'text_ja'    => '上司は個人的な問題を聞いてくれますか',
				'text_en'    => 'Does your supervisor listen to your personal problems?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-4',
				'section'    => 'C',
				'number'     => 4,
				'text_ja'    => '職場の同僚はどのくらい気軽に話ができますか',
				'text_en'    => 'How easily can you talk to your colleagues?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-5',
				'section'    => 'C',
				'number'     => 5,
				'text_ja'    => '職場の同僚はどのくらい頼りになりますか',
				'text_en'    => 'How reliable are your colleagues?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-6',
				'section'    => 'C',
				'number'     => 6,
				'text_ja'    => '職場の同僚は個人的な問題を聞いてくれますか',
				'text_en'    => 'Do your colleagues listen to your personal problems?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-7',
				'section'    => 'C',
				'number'     => 7,
				'text_ja'    => '配偶者、家族、友人等はどのくらい気軽に話ができますか',
				'text_en'    => 'How easily can you talk to your spouse, family, or friends?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-8',
				'section'    => 'C',
				'number'     => 8,
				'text_ja'    => '配偶者、家族、友人等はどのくらい頼りになりますか',
				'text_en'    => 'How reliable are your spouse, family, or friends?',
				'is_reverse' => true,
			),
			array(
				'id'         => 'C-9',
				'section'    => 'C',
				'number'     => 9,
				'text_ja'    => '配偶者、家族、友人等は個人的な問題を聞いてくれますか',
				'text_en'    => 'Does your spouse, family, or friends listen to your personal problems?',
				'is_reverse' => true,
			),
		);
	}

	/**
	 * Section D: Satisfaction (2 items, reference only, not scored).
	 *
	 * @return array
	 */
	private static function get_section_d() {
		return array(
			array(
				'id'         => 'D-1',
				'section'    => 'D',
				'number'     => 1,
				'text_ja'    => '仕事に満足だ',
				'text_en'    => 'I am satisfied with my work',
				'is_reverse' => false,
			),
			array(
				'id'         => 'D-2',
				'section'    => 'D',
				'number'     => 2,
				'text_ja'    => '家庭生活に満足だ',
				'text_en'    => 'I am satisfied with my home life',
				'is_reverse' => false,
			),
		);
	}
}
