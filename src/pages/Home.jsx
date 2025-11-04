import { motion } from 'framer-motion';
import TopicCard from '../components/TopicCard';
import { Link } from 'react-router-dom';

const Home = () => {
  const topics = [
    {
      title: 'Треугольники',
      description: 'Изучите виды треугольников, теорему Пифагора и признаки равенства',
      path: '/triangles',
      icon: '📐',
    },
    {
      title: 'Четырёхугольники',
      description: 'Параллелограмм, ромб, прямоугольник, квадрат и трапеция',
      path: '/quadrilaterals',
      icon: '▱',
    },
    {
      title: 'Окружность',
      description: 'Радиус, диаметр, центральные и вписанные углы',
      path: '/circles',
      icon: '⭕',
    },
    {
      title: 'Площади фигур',
      description: 'Формулы площадей основных геометрических фигур',
      path: '/areas',
      icon: '📏',
    },
    {
      title: 'Векторы',
      description: 'Операции с векторами, скалярное произведение',
      path: '/vectors',
      icon: '➡️',
    },
  ];

  return (
    <div className="space-y-6 md:space-y-8">
      {/* Заголовок */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center py-8 md:py-12"
      >
        <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4">
          Геометрия 7-9 класс
        </h1>
        <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto px-4">
          Интерактивный учебник по геометрии с визуализациями и практическими заданиями
        </p>
      </motion.div>

      {/* Карточки тем */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
        {topics.map((topic, index) => (
          <motion.div
            key={topic.path}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
          >
            <Link to={topic.path}>
              <div className="card hover:scale-105 transform transition-transform cursor-pointer h-full">
                <div className="text-4xl md:text-5xl mb-4">{topic.icon}</div>
                <h3 className="text-xl md:text-2xl font-bold text-gray-800 mb-2">
                  {topic.title}
                </h3>
                <p className="text-sm md:text-base text-gray-600">
                  {topic.description}
                </p>
              </div>
            </Link>
          </motion.div>
        ))}
      </div>

      {/* Информационная секция */}
      <TopicCard
        title="Как пользоваться сайтом?"
        className="mt-8"
      >
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
          <div className="text-center p-4">
            <div className="text-3xl md:text-4xl mb-3">📚</div>
            <h4 className="font-semibold mb-2 text-sm md:text-base">1. Изучите теорию</h4>
            <p className="text-xs md:text-sm text-gray-600">
              Читайте определения и формулы
            </p>
          </div>
          <div className="text-center p-4">
            <div className="text-3xl md:text-4xl mb-3">🎨</div>
            <h4 className="font-semibold mb-2 text-sm md:text-base">2. Взаимодействуйте</h4>
            <p className="text-xs md:text-sm text-gray-600">
              Перемещайте точки на графиках
            </p>
          </div>
          <div className="text-center p-4">
            <div className="text-3xl md:text-4xl mb-3">✅</div>
            <h4 className="font-semibold mb-2 text-sm md:text-base">3. Решайте задачи</h4>
            <p className="text-xs md:text-sm text-gray-600">
              Проверяйте свои знания
            </p>
          </div>
        </div>
      </TopicCard>
    </div>
  );
};

export default Home;
