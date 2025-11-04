import { motion, AnimatePresence } from 'framer-motion';
import { Link, useLocation } from 'react-router-dom';

const Sidebar = ({ isOpen, setIsOpen }) => {
  const location = useLocation();

  const sections = [
    {
      title: 'Базовые понятия',
      path: '/',
      topics: [
        'Точка, прямая, отрезок',
        'Виды углов',
        'Смежные углы',
        'Параллельные прямые',
      ],
    },
    {
      title: 'Треугольники',
      path: '/triangles',
      topics: [
        'Виды треугольников',
        'Признаки равенства',
        'Теорема Пифагора',
        'Высоты и медианы',
      ],
    },
    {
      title: 'Четырёхугольники',
      path: '/quadrilaterals',
      topics: [
        'Параллелограмм',
        'Прямоугольник',
        'Ромб и квадрат',
        'Трапеция',
      ],
    },
    {
      title: 'Окружность',
      path: '/circles',
      topics: [
        'Радиус и диаметр',
        'Касательная',
        'Центральные углы',
        'Вписанные углы',
      ],
    },
    {
      title: 'Площади',
      path: '/areas',
      topics: [
        'Площадь треугольника',
        'Площадь четырёхугольника',
        'Площадь круга',
      ],
    },
    {
      title: 'Векторы',
      path: '/vectors',
      topics: [
        'Определение вектора',
        'Операции с векторами',
        'Скалярное произведение',
      ],
    },
  ];

  const sidebarVariants = {
    open: {
      x: 0,
      transition: {
        type: 'spring',
        stiffness: 300,
        damping: 30,
      },
    },
    closed: {
      x: '-100%',
      transition: {
        type: 'spring',
        stiffness: 300,
        damping: 30,
      },
    },
  };

  return (
    <>
      {/* Мобильная версия */}
      <AnimatePresence>
        {isOpen && (
          <motion.aside
            variants={sidebarVariants}
            initial="closed"
            animate="open"
            exit="closed"
            className="fixed left-0 top-16 bottom-0 w-64 bg-white shadow-xl z-40 overflow-y-auto md:hidden"
          >
            <SidebarContent
              sections={sections}
              location={location}
              setIsOpen={setIsOpen}
            />
          </motion.aside>
        )}
      </AnimatePresence>

      {/* Десктопная версия */}
      <aside className="hidden md:block fixed left-0 top-16 bottom-0 w-64 bg-white shadow-lg overflow-y-auto z-30">
        <SidebarContent
          sections={sections}
          location={location}
          setIsOpen={setIsOpen}
        />
      </aside>
    </>
  );
};

const SidebarContent = ({ sections, location, setIsOpen }) => {
  return (
    <div className="p-4">
      <h2 className="text-lg font-bold text-gray-800 mb-4">Разделы</h2>
      <nav className="space-y-2">
        {sections.map((section) => (
          <div key={section.path}>
            <Link
              to={section.path}
              onClick={() => setIsOpen(false)}
              className={`block px-4 py-2 rounded-lg font-medium transition-colors ${
                location.pathname === section.path
                  ? 'bg-blue-100 text-blue-700'
                  : 'text-gray-700 hover:bg-gray-100'
              }`}
            >
              {section.title}
            </Link>
            <ul className="ml-4 mt-1 space-y-1">
              {section.topics.map((topic, idx) => (
                <li
                  key={idx}
                  className="text-sm text-gray-600 px-4 py-1 hover:text-blue-600 cursor-pointer"
                >
                  {topic}
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
    </div>
  );
};

export default Sidebar;
