import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';

const Navbar = ({ sidebarOpen, setSidebarOpen }) => {
  const navItems = [
    { name: 'Главная', path: '/' },
    { name: 'Треугольники', path: '/triangles' },
    { name: 'Четырёхугольники', path: '/quadrilaterals' },
    { name: 'Окружности', path: '/circles' },
    { name: 'Площади', path: '/areas' },
    { name: 'Векторы', path: '/vectors' },
  ];

  return (
    <motion.nav
      initial={{ y: -100 }}
      animate={{ y: 0 }}
      className="bg-white shadow-md sticky top-0 z-40"
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Логотип и бургер-меню */}
          <div className="flex items-center gap-4">
            {/* Бургер-меню (только на мобильных) */}
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="md:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors"
              aria-label="Меню"
            >
              <svg
                className="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                {sidebarOpen ? (
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M6 18L18 6M6 6l12 12"
                  />
                ) : (
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                )}
              </svg>
            </button>

            {/* Логотип */}
            <Link to="/" className="flex items-center gap-2">
              <div className="bg-blue-600 text-white w-8 h-8 rounded-lg flex items-center justify-center font-bold">
                Г
              </div>
              <span className="text-xl font-bold text-gray-800 hidden sm:inline">
                Геометрия
              </span>
            </Link>
          </div>

          {/* Навигация (скрыта на мобильных) */}
          <div className="hidden lg:flex items-center gap-1">
            {navItems.map((item) => (
              <Link
                key={item.path}
                to={item.path}
                className="px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100 hover:text-blue-600 transition-colors text-sm font-medium"
              >
                {item.name}
              </Link>
            ))}
          </div>

          {/* Кнопка прогресса */}
          <button className="hidden md:block btn-primary text-sm">
            Прогресс
          </button>
        </div>
      </div>
    </motion.nav>
  );
};

export default Navbar;
