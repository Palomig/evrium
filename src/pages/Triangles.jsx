import TopicCard from '../components/TopicCard';
import InteractiveFigure from '../components/InteractiveFigure';
import Formula from '../components/Formula';
import Quiz from '../components/Quiz';

const Triangles = () => {
  const initTriangleBoard = (board) => {
    // Создаём треугольник с подвижными вершинами
    const A = board.create('point', [-2, 0], {
      name: 'A',
      size: 3,
      fillColor: '#3B82F6',
    });
    const B = board.create('point', [2, 0], {
      name: 'B',
      size: 3,
      fillColor: '#3B82F6',
    });
    const C = board.create('point', [0, 3], {
      name: 'C',
      size: 3,
      fillColor: '#3B82F6',
    });

    // Стороны треугольника
    const a = board.create('segment', [B, C], { strokeColor: '#10B981', strokeWidth: 2 });
    const b = board.create('segment', [A, C], { strokeColor: '#10B981', strokeWidth: 2 });
    const c = board.create('segment', [A, B], { strokeColor: '#10B981', strokeWidth: 2 });

    // Подписи длин сторон
    board.create('text', [
      () => (B.X() + C.X()) / 2,
      () => (B.Y() + C.Y()) / 2 + 0.3,
      () => `a = ${Math.sqrt(Math.pow(B.X() - C.X(), 2) + Math.pow(B.Y() - C.Y(), 2)).toFixed(2)}`,
    ], { fontSize: 12 });

    board.create('text', [
      () => (A.X() + C.X()) / 2 - 0.5,
      () => (A.Y() + C.Y()) / 2,
      () => `b = ${Math.sqrt(Math.pow(A.X() - C.X(), 2) + Math.pow(A.Y() - C.Y(), 2)).toFixed(2)}`,
    ], { fontSize: 12 });

    board.create('text', [
      () => (A.X() + B.X()) / 2,
      () => (A.Y() + B.Y()) / 2 - 0.3,
      () => `c = ${Math.sqrt(Math.pow(A.X() - B.X(), 2) + Math.pow(A.Y() - B.Y(), 2)).toFixed(2)}`,
    ], { fontSize: 12 });
  };

  return (
    <div className="space-y-6 md:space-y-8">
      <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
        Треугольники
      </h1>

      <TopicCard
        title="Определение"
        description="Треугольник — это геометрическая фигура, образованная тремя отрезками, которые соединяют три точки, не лежащие на одной прямой."
      >
        <div className="space-y-4">
          <p className="text-sm md:text-base text-gray-700">
            Вершины треугольника обозначаются заглавными латинскими буквами: A, B, C.
            Стороны обозначаются строчными буквами: a, b, c (противоположные соответствующим вершинам).
          </p>
        </div>
      </TopicCard>

      <TopicCard title="Интерактивный треугольник">
        <p className="text-sm md:text-base text-gray-600 mb-4">
          Перемещайте вершины треугольника, чтобы изменить его форму и увидеть, как меняются длины сторон.
        </p>
        <InteractiveFigure id="triangle-board" initBoard={initTriangleBoard} />
      </TopicCard>

      <TopicCard title="Виды треугольников">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
          <div className="p-4 bg-blue-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">По сторонам</h4>
            <ul className="space-y-2 text-xs md:text-sm text-gray-700">
              <li>• <strong>Разносторонний</strong> — все стороны разные</li>
              <li>• <strong>Равнобедренный</strong> — две стороны равны</li>
              <li>• <strong>Равносторонний</strong> — все стороны равны</li>
            </ul>
          </div>
          <div className="p-4 bg-green-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">По углам</h4>
            <ul className="space-y-2 text-xs md:text-sm text-gray-700">
              <li>• <strong>Остроугольный</strong> — все углы острые</li>
              <li>• <strong>Прямоугольный</strong> — один угол 90°</li>
              <li>• <strong>Тупоугольный</strong> — один угол тупой</li>
            </ul>
          </div>
        </div>
      </TopicCard>

      <TopicCard title="Теорема Пифагора">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          В прямоугольном треугольнике квадрат гипотенузы равен сумме квадратов катетов:
        </p>
        <div className="bg-blue-50 p-4 md:p-6 rounded-lg">
          <Formula latex="c^2 = a^2 + b^2" display={true} />
        </div>
        <p className="text-xs md:text-sm text-gray-600 mt-4">
          где <em>c</em> — гипотенуза, <em>a</em> и <em>b</em> — катеты
        </p>
      </TopicCard>

      <TopicCard title="Площадь треугольника">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Площадь треугольника вычисляется по формуле:
        </p>
        <div className="bg-green-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = \\frac{1}{2} \\cdot a \\cdot h" display={true} />
        </div>
        <p className="text-xs md:text-sm text-gray-600 mt-4">
          где <em>a</em> — основание, <em>h</em> — высота, проведённая к этому основанию
        </p>
      </TopicCard>

      <Quiz
        question="Найдите гипотенузу прямоугольного треугольника, если катеты равны 3 и 4."
        correctAnswer={5}
        hint="Используйте теорему Пифагора: c² = 3² + 4²"
      />
    </div>
  );
};

export default Triangles;
