// Центральная конфигурация сайта.
// Агент: меняйте значения здесь — они подтянутся во все страницы.
// TODO-метки помечают данные, которые нужно заменить на реальные.

export const site = {
  // TODO: реальное ФИО репетитора
  tutorName: 'Имя Фамилия',
  // TODO: короткая легенда (опыт, образование, регалии)
  tutorBio: 'Репетитор по математике, информатике и физике с многолетним опытом подготовки школьников Чехова к ОГЭ и ЕГЭ.',
  city: 'Чехов',
  region: 'Московская область',
  street: 'ул. Московская, 87/1',
  // TODO: индекс — уточнить (для Чехова Московская обычно 142300–142306)
  postalCode: '',

  phone: '+7 910 301-71-10',
  phoneHref: 'tel:+79103017110',
  whatsapp: 'https://wa.me/79103017110',
  telegram: 'https://t.me/Palomig',
  email: 'tutor@эвриум.рф',

  // Каноничный домен (используется в OG/canonical/sitemap)
  domain: 'https://эвриум.рф',
  basePath: '',

  // Адрес для отображения (футер, контакты, страница кабинета)
  addressPublic: 'г. Чехов, ул. Московская, 87/1',
  // TODO: координаты для Schema.org (уточнить точные)
  geo: { lat: 55.1456, lng: 37.4538 }, // примерные координаты Чехова

  // TODO: реальные цены
  prices: {
    diagnostic: { label: 'Диагностика', duration: '45–60 мин', price: 'бесплатно' },
    single60:   { label: 'Индивидуальное занятие', duration: '60 мин', price: 'от 1 500 ₽' },
    single90:   { label: 'Индивидуальное занятие', duration: '90 мин', price: 'от 2 200 ₽' },
    oge:        { label: 'Подготовка к ОГЭ',       duration: '90 мин', price: 'от 2 200 ₽' },
    ege:        { label: 'Подготовка к ЕГЭ',       duration: '90 мин', price: 'от 2 500 ₽' },
  },

  // Аналитика
  // TODO: ID счётчика Яндекс Метрики
  yandexMetrikaId: '00000000',

  // География (для блока «откуда приезжают»)
  serviceAreas: [
    'Чехов',
    'Венюково',
    'Манушкино',
    'Любучаны',
    'Столбовая',
    'Новый Быт',
  ],
};

/**
 * Главное меню. Группы со свойством `children` рендерятся как дропдауны.
 * «Главная» убрана из меню — клик по логотипу ведёт на /.
 */
export type NavItem = {
  label: string;
  href?: string;
  children?: { label: string; href: string }[];
};

export const nav: NavItem[] = [
  {
    label: 'Предметы',
    children: [
      { label: 'Математика',  href: '/matematika/' },
      { label: 'Информатика', href: '/informatika/' },
      { label: 'Физика',      href: '/fizika/' },
    ],
  },
  {
    label: 'Подготовка',
    children: [
      { label: 'ОГЭ', href: '/oge/' },
      { label: 'ЕГЭ', href: '/ege/' },
    ],
  },
  {
    label: 'О кабинете',
    children: [
      { label: 'Цены',    href: '/ceny/' },
      { label: 'Кабинет', href: '/kabinet/' },
      { label: 'Отзывы',  href: '/otzyvy/' },
    ],
  },
  { label: 'Блог',     href: '/blog/' },
  { label: 'Контакты', href: '/kontakty/' },
];
