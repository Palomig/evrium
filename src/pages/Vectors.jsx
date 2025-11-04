import TopicCard from '../components/TopicCard';
import Formula from '../components/Formula';

const Vectors = () => {
  return (
    <div className="space-y-6 md:space-y-8">
      <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
        Векторы
      </h1>

      <TopicCard
        title="Определение"
        description="Вектор — направленный отрезок, имеющий начало и конец."
      />

      <TopicCard title="Операции с векторами">
        <div className="space-y-4 md:space-y-6">
          <div className="p-4 bg-blue-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">Сложение векторов</h4>
            <Formula latex="\\vec{c} = \\vec{a} + \\vec{b}" display={true} />
          </div>

          <div className="p-4 bg-green-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">Вычитание векторов</h4>
            <Formula latex="\\vec{c} = \\vec{a} - \\vec{b}" display={true} />
          </div>

          <div className="p-4 bg-purple-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">Скалярное произведение</h4>
            <Formula latex="\\vec{a} \\cdot \\vec{b} = |\\vec{a}| \\cdot |\\vec{b}| \\cdot \\cos\\theta" display={true} />
          </div>
        </div>
      </TopicCard>

      <TopicCard title="Координаты вектора">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Вектор в координатной плоскости задаётся парой чисел:
        </p>
        <div className="bg-yellow-50 p-4 md:p-6 rounded-lg">
          <Formula latex="\\vec{a} = (x, y)" display={true} />
        </div>
      </TopicCard>

      <TopicCard title="Длина вектора">
        <div className="bg-red-50 p-4 md:p-6 rounded-lg">
          <Formula latex="|\\vec{a}| = \\sqrt{x^2 + y^2}" display={true} />
        </div>
      </TopicCard>
    </div>
  );
};

export default Vectors;
